<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiEphemeralTokenService
{
    /**
     * Create a short-lived token for browser Live API sessions.
     *
     * @return array{token: string, expireTime: string|null, liveModel: string, transcriptionModel: string}
     */
    public function createLiveSessionToken(int $uses = 15, int $expireMinutes = 45, int $newSessionMinutes = 10): array
    {
        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $expireTime = now()->utc()->addMinutes($expireMinutes)->format('Y-m-d\TH:i:s\Z');
        $newSessionExpireTime = now()->utc()->addMinutes($newSessionMinutes)->format('Y-m-d\TH:i:s\Z');

        $response = Http::timeout(20)
            ->connectTimeout(10)
            ->withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
            ->post('https://generativelanguage.googleapis.com/v1beta/auth_tokens', [
                'expireTime' => $expireTime,
                'newSessionExpireTime' => $newSessionExpireTime,
                'uses' => $uses,
            ]);

        if (! $response->successful()) {
            Log::warning('Gemini ephemeral token creation failed.', [
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            throw new RuntimeException('Unable to create live session token.');
        }

        $token = $response->json('name');

        if (! is_string($token) || trim($token) === '') {
            Log::warning('Gemini ephemeral token response missing token name.');

            throw new RuntimeException('Invalid live session token response.');
        }

        return [
            'token' => $token,
            'expireTime' => $response->json('expireTime'),
            'liveModel' => (string) config('services.gemini.live_model'),
            'transcriptionModel' => (string) config('services.gemini.transcription_model'),
        ];
    }
}
