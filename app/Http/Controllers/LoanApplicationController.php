<?php

namespace App\Http\Controllers;

use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\User;
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
        $users = [];

        if (!$request->user()->isAdmin()) {
            $query->whereHas('applicant', function ($q) use ($request) {
                $q->where('created_by', $request->user()->id);
            });
        } else {
            $users = User::whereIn('role', ['admin', 'analyst'])->orderBy('name')->get();

            if ($request->filled('user_id')) {
                $query->whereHas('applicant', function ($q) use ($request) {
                    $q->where('created_by', $request->user_id);
                });
            }
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

        $applications = $query->latest()->paginate(10)->appends($request->query());

        return view('loan-applications.index', compact('applications', 'users'));
    }

    public function downloadTemplate()
    {
        $tempFile = storage_path('app/temp_loan_applicants_template.xlsx');

        $textStyle = (new \OpenSpout\Common\Entity\Style\Style())->setFormat('@');
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempFile)->noHeaderRow();

        // Force phone cells as Excel text so leading zeros are kept while typing.
        $writer->addRow(new \OpenSpout\Common\Entity\Row([
            \OpenSpout\Common\Entity\Cell::fromValue('applicant_name'),
            \OpenSpout\Common\Entity\Cell::fromValue('phone_number'),
        ]));

        foreach ([
            ['Example Applicant', '01900000001'],
            ['Another Example', '01800000002'],
        ] as [$name, $phone]) {
            $writer->addRow(new \OpenSpout\Common\Entity\Row([
                \OpenSpout\Common\Entity\Cell::fromValue($name),
                new \OpenSpout\Common\Entity\Cell\StringCell($phone, $textStyle),
            ]));
        }

        unset($writer);

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
        $duplicateCount = 0;
        $invalidCount = 0;
        $rowCount = 0;
        $ownerId = $request->user()->id;

        DB::beginTransaction();
        try {
            $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($file->getRealPath(), $extension);

            $reader->getRows()->each(function (array $row) use (&$successCount, &$errorCount, &$duplicateCount, &$invalidCount, &$rowCount, $ownerId) {
                $rowCount++;

                // standardize keys
                $row = array_change_key_case($row, CASE_LOWER);
                $name = isset($row['applicant_name']) ? trim((string) $row['applicant_name']) : '';
                $phone = $row['phone_number'] ?? null;

                if ($name === '' || $phone === null || trim((string) $phone) === '') {
                    $errorCount++;
                    $invalidCount++;
                    return;
                }

                $phone = $this->normalizeImportPhone($phone);

                if ($phone === null) {
                    $errorCount++;
                    $invalidCount++;
                    return;
                }

                $phoneHash = hash('sha256', $phone);
                $reference = 'LA-' . strtoupper(Str::random(8));

                // Duplicates are scoped per recruiter/workspace owner, not globally.
                $existing = LoanApplicant::where('phone_hash', $phoneHash)
                    ->where('created_by', $ownerId)
                    ->first();

                if ($existing) {
                    $errorCount++;
                    $duplicateCount++;
                    return;
                }

                $applicant = LoanApplicant::create([
                    'name' => $name,
                    'phone' => $phone,
                    'application_reference' => $reference,
                    'created_by' => $ownerId,
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

        if ($rowCount === 0) {
            return back()->with(
                'error',
                'No applicant rows found. Download the template, add rows with applicant_name and phone_number, then import again.'
            );
        }

        if ($successCount === 0 && $duplicateCount > 0 && $invalidCount === 0) {
            return back()->with(
                'error',
                "No new applicants imported. All {$duplicateCount} phone number(s) already exist in your list."
            );
        }

        $message = "Import completed. Successfully imported: {$successCount}.";
        if ($errorCount > 0) {
            $parts = [];
            if ($duplicateCount > 0) {
                $parts[] = "duplicates in your list: {$duplicateCount}";
            }
            if ($invalidCount > 0) {
                $parts[] = "invalid rows: {$invalidCount}";
            }
            $message .= ' Skipped (' . implode(', ', $parts) . ').';
        }

        return back()->with('success', $message);
    }

    /**
     * Normalize Bangladesh phone values from CSV/XLSX.
     * Accepts: 01900000001, +8801900000001, 8801900000001, 1900000001,
     * Excel-stripped leading zeros, and scientific-notation number cells.
     */
    private function normalizeImportPhone(mixed $phone): ?string
    {
        if (is_float($phone) || is_int($phone)) {
            // Prefer exact digit string for large numeric Excel cells.
            $phone = number_format((float) $phone, 0, '', '');
        } else {
            $phone = trim((string) $phone);
            // Excel / paste sometimes keeps a leading apostrophe for text cells.
            $phone = ltrim($phone, "'`");

            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)[eE]([+\-]?[0-9]+)$/', $phone, $matches) === 1) {
                $phone = number_format((float) $phone, 0, '', '');
            }
        }

        $phone = preg_replace('/[^0-9]/', '', (string) $phone);

        if ($phone === null || $phone === '') {
            return null;
        }

        // +880 / 880 / 00880 country codes → local 0...
        if (str_starts_with($phone, '880')) {
            $phone = '0' . substr($phone, 3);
        } elseif (str_starts_with($phone, '00880')) {
            $phone = '0' . substr($phone, 5);
        }

        // Excel drops leading 0 → 10-digit local mobile starting with 1.
        if (preg_match('/^1\d{9}$/', $phone) === 1) {
            $phone = '0' . $phone;
        }

        // Final accepted local BD mobile shape: 01XXXXXXXXX (11 digits).
        if (preg_match('/^01\d{9}$/', $phone) !== 1) {
            return null;
        }

        return $phone;
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

        return response()->json($this->applicationStatusPayload($application));
    }

    public function statusSnapshot(Request $request)
    {
        Gate::authorize('viewAny', LoanApplication::class);

        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }

        $ids = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $ids)));
        if ($ids === []) {
            return response()->json(['applications' => []]);
        }

        $query = LoanApplication::query()->whereIn('id', $ids);

        if (!$request->user()->isAdmin()) {
            $query->whereHas('applicant', function ($q) use ($request) {
                $q->where('created_by', $request->user()->id);
            });
        }

        $applications = $query->get(['id', 'status', 'outcome', 'submitted_at', 'extracted_data', 'calculation_data']);

        $snapshot = [];
        foreach ($applications as $application) {
            $snapshot[$application->id] = $this->applicationStatusPayload($application);
        }

        return response()->json(['applications' => $snapshot]);
    }

    private function applicationStatusPayload(LoanApplication $application): array
    {
        $hasExtractedData = is_array($application->extracted_data);
        $hasCalculationData = is_array($application->calculation_data)
            && (
                isset($application->calculation_data['eligible_amount'])
                || isset($application->calculation_data['assessment_status'])
            );

        $isReportReady = $hasExtractedData && in_array($application->status, ['assessed', 'needs_review'], true);

        return [
            'status' => $application->status,
            'outcome' => $application->outcome,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'has_extracted_data' => $hasExtractedData,
            'has_calculation_data' => $hasCalculationData,
            'is_report_ready' => $isReportReady,
        ];
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
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

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
