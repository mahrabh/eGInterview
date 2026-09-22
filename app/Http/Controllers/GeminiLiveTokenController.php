<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Services\GeminiEphemeralTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Interview;
use RuntimeException;

class GeminiLiveTokenController extends Controller
{
    public function __construct(
        private readonly GeminiEphemeralTokenService $tokenService,
    ) {}

    public function recruitment(Request $request, string $identifier): JsonResponse
    {
        $this->resolveRecruitmentInterview($identifier);

        return $this->issueToken('recruitment');
    }

    public function loan(Request $request, string $token): JsonResponse
    {
        LoanApplication::query()
            ->where('public_token_hash', hash('sha256', $token))
            ->where('public_token_expiry', '>', now())
            ->whereNull('submitted_at')
            ->firstOrFail();

        return $this->issueToken('loan');
    }

    public function bankOpening(Request $request, string $token): JsonResponse
    {
        $application = \App\Models\BankOpeningApplication::findByPublicToken($token);

        if (! $application || $application->isInterviewFinished()) {
            abort(404);
        }

        return $this->issueToken('bank_opening');
    }

    public function logTranscriptionFallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context' => 'required|string|in:loan,recruitment,bank_opening',
            'reason' => 'required|string|max:120',
        ]);

        Log::notice('Dedicated transcription unavailable; using Live input transcription fallback.', [
            'context' => $validated['context'],
            'reason' => $validated['reason'],
        ]);

        return response()->json(['logged' => true]);
    }

    private function issueToken(string $context): JsonResponse
    {
        try {
            $payload = $this->tokenService->createLiveSessionToken();

            return response()->json([
                ...$payload,
                'context' => $context,
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Live token endpoint failed.', [
                'context' => $context,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to start secure live session. Please try again.',
            ], 503);
        }
    }

    private function resolveRecruitmentInterview(string $identifier): Interview
    {
        if (Str::isUuid($identifier)) {
            return Interview::query()
                ->where('id', $identifier)
                ->orWhere('public_url', $identifier)
                ->where(function ($query) {
                    $query->whereNull('link_expires_at')
                        ->orWhere('link_expires_at', '>', now());
                })
                ->firstOrFail();
        }

        return Interview::query()
            ->where('public_url', $identifier)
            ->where(function ($query) {
                $query->whereNull('link_expires_at')
                    ->orWhere('link_expires_at', '>', now());
            })
            ->firstOrFail();
    }
}
