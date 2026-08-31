<?php

namespace App\Http\Controllers;

use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Gate;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LoanApplication::class);

        $query = LoanApplication::with(['applicant']);
        
        if (!$request->user()->isAdmin()) {
            $query->whereHas('applicant', function ($q) use ($request) {
                $q->where('created_by', $request->user()->id);
            });
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('applicant', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('application_reference', 'like', "%{$searchTerm}%")
                  ->orWhere('phone_hash', hash('sha256', $searchTerm));
            });
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('loan_type')) {
            $query->where('loan_type', $request->loan_type);
        }

        $applications = $query->latest()->paginate(10);

        return view('loan-applications.index', compact('applications'));
    }

    public function downloadTemplate()
    {
        $tempFile = storage_path('app/temp_loan_applicants_template.xlsx');
        
        \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile)
            ->addRow([
                'applicant_name' => 'John Doe',
                'phone_number' => '01712345678',
            ]);

        return response()->download($tempFile, 'loan_applicants_template.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request)
    {
        Gate::authorize('create', LoanApplication::class);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
        ]);

        $file = $request->file('csv_file');
        $extension = $file->getClientOriginalExtension() === 'txt' ? 'csv' : $file->getClientOriginalExtension();
        
        $successCount = 0;
        $errorCount = 0;

        DB::beginTransaction();
        try {
            $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($file->getRealPath(), $extension);
            
            $reader->getRows()->each(function(array $row) use (&$successCount, &$errorCount, $request) {
                // standardize keys
                $row = array_change_key_case($row, CASE_LOWER);
                $name = $row['applicant_name'] ?? null;
                $phone = $row['phone_number'] ?? null;

                if (empty($name) || empty($phone)) {
                    $errorCount++;
                    return; // continue
                }
                
                // Normalize phone: strip non-numeric, convert 880 prefix to 0
                $phone = preg_replace('/[^0-9]/', '', (string)$phone);
                if (str_starts_with($phone, '880')) {
                    $phone = '0' . substr($phone, 3);
                }

                $phoneHash = hash('sha256', $phone);
                $reference = 'LA-' . strtoupper(Str::random(8));

                $existing = LoanApplicant::where('phone_hash', $phoneHash)->first();

                if ($existing) {
                    $errorCount++;
                    return;
                }

                $applicant = LoanApplicant::create([
                    'name' => $name,
                    'phone' => $phone,
                    'application_reference' => $reference,
                    'created_by' => $request->user()->id,
                ]);

                LoanApplication::create([
                    'loan_applicant_id' => $applicant->id,
                    'loan_type' => null, // null until confirmed in AI interview
                    'status' => 'draft',
                ]);

                $successCount++;
            });
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }

        $message = "Import completed. Successfully imported: $successCount.";
        if ($errorCount > 0) {
            $message .= " Skipped (duplicates/invalid): $errorCount.";
        }

        return back()->with('success', $message);
    }

    public function destroy($id)
    {
        $application = LoanApplication::findOrFail($id);
        Gate::authorize('delete', $application);
        
        $application->applicant->delete();

        return back()->with('success', 'Application deleted successfully.');
    }

    public function generateLink($id)
    {
        $application = LoanApplication::findOrFail($id);
        Gate::authorize('update', $application);

        $expiry = now()->addHours(48);
        // Deterministic token so UI can recreate and display it persistently without DB plaintext storage
        $token = hash_hmac('sha256', $application->id . $expiry->timestamp, config('app.key'));
        
        $application->public_token_hash = hash('sha256', $token);
        $application->public_token_expiry = $expiry;
        $application->save();

        return back()->with('success', 'Interview link generated successfully!')
                     ->with('generated_link_url', url("/loan-interview/{$token}"))
                     ->with('generated_link_id', $application->id);
    }
    
    public function report($id)
    {
        $application = LoanApplication::with(['applicant', 'rule', 'events'])->findOrFail($id);
        Gate::authorize('view', $application);

        return view('loan-applications.report', compact('application'));
    }

    public function status($id)
    {
        $application = LoanApplication::findOrFail($id);
        Gate::authorize('view', $application);

        $hasExtractedData = is_array($application->extracted_data);
        $hasCalculationData = is_array($application->calculation_data)
            && (
                isset($application->calculation_data['eligible_amount'])
                || isset($application->calculation_data['assessment_status'])
            );

        $isReportReady = $hasExtractedData && in_array($application->status, ['assessed', 'needs_review'], true);

        return response()->json([
            'status' => $application->status,
            'outcome' => $application->outcome,
            'has_extracted_data' => $hasExtractedData,
            'has_calculation_data' => $hasCalculationData,
            'is_report_ready' => $isReportReady,
        ]);
    }

    public function recalculate(Request $request, $id)
    {
        $application = LoanApplication::findOrFail($id);
        Gate::authorize('update', $application);

        $request->validate([
            'extracted_data' => 'required|array',
        ]);

        $currentData = $application->extracted_data ?? [];
        $newData = $request->extracted_data ?? [];
        
        foreach ($newData as $key => $val) {
            if (is_array($val) && isset($val['value'])) {
                if (!isset($currentData[$key]) || !is_array($currentData[$key])) {
                    $currentData[$key] = ['value' => $val['value'], 'evidence' => 'Manually updated', 'confidence' => 100];
                } else {
                    $currentData[$key]['value'] = $val['value'];
                    $currentData[$key]['evidence'] = 'Manually updated (Original: ' . ($currentData[$key]['evidence'] ?? 'None') . ')';
                    $currentData[$key]['confidence'] = 100;
                }
            } else {
                $currentData[$key] = $val;
            }
        }
        $application->extracted_data = $currentData;
        
        // Ensure values are copied correctly
        $extracted = $application->extracted_data;
        $application->loan_type = $extracted['loan_type']['value'] ?? $application->loan_type;
        $application->requested_amount = isset($extracted['requested_amount']['value']) && is_numeric($extracted['requested_amount']['value']) ? $extracted['requested_amount']['value'] : $application->requested_amount;
        $application->purpose = $extracted['loan_purpose']['value'] ?? $application->purpose;
        $application->employment_status = $extracted['income_source']['value'] ?? $application->employment_status;
        $application->income_source = $extracted['income_source']['value'] ?? $application->income_source;
        $application->employer_name = $extracted['employer_name']['value'] ?? $application->employer_name;
        $application->monthly_income = isset($extracted['exact_monthly_income']['value']) && is_numeric($extracted['exact_monthly_income']['value']) ? $extracted['exact_monthly_income']['value'] : $application->monthly_income;
        $application->other_monthly_income = isset($extracted['other_regular_monthly_income']['value']) && is_numeric($extracted['other_regular_monthly_income']['value']) ? $extracted['other_regular_monthly_income']['value'] : $application->other_monthly_income;
        $application->existing_emi = isset($extracted['existing_monthly_obligations']['value']) && is_numeric($extracted['existing_monthly_obligations']['value']) ? $extracted['existing_monthly_obligations']['value'] : $application->existing_emi;
        $application->tenure_months = isset($extracted['requested_tenure']['value']) && is_numeric($extracted['requested_tenure']['value']) ? $extracted['requested_tenure']['value'] : $application->tenure_months;
        $application->asset_value = isset($extracted['asset_value']['value']) && is_numeric($extracted['asset_value']['value']) ? $extracted['asset_value']['value'] : $application->asset_value;
        $application->down_payment = isset($extracted['down_payment']['value']) && is_numeric($extracted['down_payment']['value']) ? $extracted['down_payment']['value'] : $application->down_payment;

        $calcService = new \App\Services\LoanCalculationService();
        $calcService->calculate($application);

        return back()->with('success', 'Application recalculation successful.');
    }
    
    public function retryExtraction($id)
    {
        $application = LoanApplication::findOrFail($id);
        Gate::authorize('update', $application);

        $extractionService = new \App\Services\LoanExtractionService();
        $extractionService->extract($application);

        $fresh = $application->fresh();
        if ($fresh && is_array($fresh->extracted_data)) {
            return back()->with('success', 'Application updated successfully.');
        }

        return back()->with('success', 'Processing attempted. Please try again if fields remain empty.');
    }
}
