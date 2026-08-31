<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\LoanCalculationService;
use Carbon\Carbon;

class LoanInterviewController extends Controller
{
    public function publicSession($token)
    {
        $application = LoanApplication::where('public_token_hash', hash('sha256', $token))
            ->where('public_token_expiry', '>', now())
            ->with('applicant')
            ->firstOrFail();

        if ($application->submitted_at !== null) {
            return view('interview.completed');
        }

        // Pass to blade
        $interview = [
            'id' => $application->id,
            'public_url' => $token,
            'candidate_name' => $application->applicant->name,
            'status' => $application->status,
            'is_submitted' => false,
        ];

        return view('loan-applications.public', compact('interview'));
    }

    public function start(Request $request, $token)
    {
        $application = LoanApplication::where('public_token_hash', hash('sha256', $token))
            ->where('public_token_expiry', '>', now())
            ->with('applicant')
            ->firstOrFail();

        if ($application->submitted_at !== null) {
            return response()->json(['success' => false, 'error' => 'Application is already submitted.'], 403);
        }

        $request->validate([
            'nid' => 'required|string|min:10|max:17',
        ]);

        $nid = $request->input('nid');
        $application->applicant->nid = encrypt($nid);
        $application->applicant->nid_hash = hash('sha256', $nid);
        $application->applicant->nid_last_four = substr($nid, -4);
        $application->applicant->save();

        return response()->json(['success' => true]);
    }

    public function saveTranscript(Request $request, $token)
    {
        $application = LoanApplication::where('public_token_hash', hash('sha256', $token))
            ->firstOrFail();

        if ($application->submitted_at !== null) {
            $transcriptText = $request->input('transcript_text');
            if ($this->transcriptMatches($application, $transcriptText)) {
                return response()->json(['success' => true]);
            }

            return response()->json(['success' => false, 'error' => 'Application is already submitted.'], 403);
        }

        $transcriptText = $request->input('transcript_text');

        if (!$transcriptText) {
            return response()->json(['success' => false, 'error' => 'Empty transcript'], 400);
        }

        DB::beginTransaction();
        try {
            // Use pessimistic locking to handle concurrent tabs
            $application = LoanApplication::where('id', $application->id)->lockForUpdate()->first();

            if ($application->submitted_at !== null) {
                DB::rollBack();
                if ($this->transcriptMatches($application, $transcriptText)) {
                    return response()->json(['success' => true]);
                }

                return response()->json(['success' => false, 'error' => 'Application is already submitted.'], 403);
            }

            $application->transcript = $transcriptText;
            $application->transcript_text = $transcriptText;
            $application->submitted_at = now();
            $application->status = 'processing';
            $application->save();

            LoanApplicationEvent::create([
                'loan_application_id' => $application->id,
                'event_type' => 'transcript_saved',
                'description' => 'Interview transcript has been saved.',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Loan transcript save failed.', [
                'loan_application_id' => $application->id,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'Server Error'], 500);
        }

        // Run extraction only after the final transcript row is committed.
        try {
            $freshApplication = LoanApplication::findOrFail($application->id);
            app(\App\Services\LoanExtractionService::class)->extract($freshApplication);
        } catch (\Throwable $e) {
            Log::error('Loan extraction failed after transcript commit.', [
                'loan_application_id' => $application->id,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function transcriptMatches(LoanApplication $application, ?string $transcriptText): bool
    {
        if (!is_string($transcriptText) || trim($transcriptText) === '') {
            return false;
        }

        foreach (['transcript', 'transcript_text'] as $column) {
            $stored = $application->getAttribute($column);
            if (is_string($stored) && $stored === $transcriptText) {
                return true;
            }
        }

        return false;
    }
}
