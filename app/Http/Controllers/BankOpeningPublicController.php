<?php

namespace App\Http\Controllers;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplication;
use App\Services\BankOpeningInterviewService;
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

        return redirect()
            ->route('bank-opening.public', $token)
            ->with('success', 'Details saved. Continues to your short account-opening interview.');
    }
}
