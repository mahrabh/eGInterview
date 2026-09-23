<?php

namespace App\Http\Controllers;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplicant;
use App\Models\BankOpeningApplication;
use App\Services\BankOpeningInterviewService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankOpeningPublicController extends Controller
{
    public function __construct(private BankOpeningInterviewService $interview)
    {
    }

    public function show(string $token)
    {
        $application = BankOpeningApplication::findByPublicToken($token);

        if (! $application) {
            abort(404, 'This application link is invalid or has expired.');
        }

        $application->load('applicant');

        $readyForInterview = filled($application->applicant->name)
            && (
                filled($application->applicant->phone_masked)
                || filled($application->applicant->phone)
            );

        if (! $readyForInterview) {
            return view('bank-openings.public', [
                'application' => $application,
                'token' => $token,
                'expired' => false,
            ]);
        }

        return view('bank-openings.interview', [
            'application' => $application,
            'token' => $token,
            'interview' => $this->interview->bootstrap($application, $token),
        ]);
    }

    public function submitInformation(Request $request, string $token)
    {
        $application = BankOpeningApplication::findByPublicToken($token);

        if (! $application) {
            abort(404, 'This application link is invalid or has expired.');
        }

        $application->load('applicant');

        if (in_array($application->stage, [
            BankOpeningStage::Completed,
            BankOpeningStage::Submitted,
            BankOpeningStage::UnderReview,
        ], true)) {
            return redirect()
                ->route('bank-opening.public', $token)
                ->with('error', 'This application can no longer be edited via the public link.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $applicant = $application->applicant;
        $phoneHash = $this->phoneHash($validated['phone']);

        // Same officer + same phone on a different applicant → block with a clear message.
        // Different officers may still use the same phone (unique is per created_by).
        if (
            $applicant->created_by
            && $phoneHash
            && BankOpeningApplicant::query()
                ->where('phone_hash', $phoneHash)
                ->where('created_by', $applicant->created_by)
                ->where('id', '!=', $applicant->id)
                ->exists()
        ) {
            return redirect()
                ->route('bank-opening.public', $token)
                ->withInput()
                ->with(
                    'error',
                    'This phone number is already used for another application.'
                );
        }

        try {
            DB::transaction(function () use ($application, $validated) {
                $applicant = $application->applicant;
                $applicant->name = $validated['name'];
                $applicant->phone = $validated['phone'];
                $applicant->save();

                $application->information_submitted_at = $application->information_submitted_at ?? now();

                if ($application->stage === BankOpeningStage::Invited) {
                    $application->stage = BankOpeningStage::InformationSubmitted;
                }

                $application->save();

                $application->recordEvent('information_submitted', [
                    'fields' => ['name', 'phone'],
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            return redirect()
                ->route('bank-opening.public', $token)
                ->withInput()
                ->with(
                    'error',
                    'This phone number is already used for another application under this officer. Please use a different number, or continue with the existing interview link.'
                );
        }

        return redirect()
            ->route('bank-opening.public', $token)
            ->with('success', 'Details saved. Continues to your short account-opening interview.');
    }

    private function phoneHash(string $phone): string
    {
        $normalized = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($normalized, '880') && strlen($normalized) > 3) {
            $normalized = '0'.substr($normalized, 3);
        }

        return hash('sha256', $normalized);
    }
}
