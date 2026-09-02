<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interview;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiLiveTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_live_token_endpoint_returns_models_without_exposing_api_key(): void
    {
        config([
            'services.gemini.key' => 'server-only-key',
            'services.gemini.live_model' => 'gemini-3.1-flash-live-preview',
            'services.gemini.transcription_model' => 'gemini-3.5-transcribe-live',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/v1beta/auth_tokens' => Http::response([
                'name' => 'auth_tokens/test-token-value',
                'expireTime' => now()->addMinutes(30)->toIso8601String(),
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create([
            'name' => 'Token Applicant',
            'phone' => '01710000099',
            'application_reference' => 'LA-TOKEN',
            'created_by' => $user->id,
        ]);

        $token = 'loan-public-token';
        LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'status' => 'draft',
            'public_token_hash' => hash('sha256', $token),
            'public_token_expiry' => now()->addDay(),
        ]);

        $response = $this->postJson("/loan-interview/{$token}/live-token");

        $response->assertOk()
            ->assertJsonPath('token', 'auth_tokens/test-token-value')
            ->assertJsonPath('liveModel', 'gemini-3.1-flash-live-preview')
            ->assertJsonPath('transcriptionModel', 'gemini-3.5-transcribe-live');

        $response->assertJsonMissing(['apiKey' => 'server-only-key']);
        $response->assertJsonMissing(['GEMINI_API_KEY' => 'server-only-key']);
    }

    public function test_recruitment_live_token_endpoint_returns_ephemeral_token(): void
    {
        config([
            'services.gemini.key' => 'server-only-key',
            'services.gemini.live_model' => 'gemini-3.1-flash-live-preview',
            'services.gemini.transcription_model' => 'gemini-3.5-transcribe-live',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/v1beta/auth_tokens' => Http::response([
                'name' => 'auth_tokens/recruitment-token',
                'expireTime' => now()->addMinutes(30)->toIso8601String(),
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'recruiter']);

        $interview = Interview::create([
            'candidate_name' => 'Token Candidate',
            'applied_role' => 'Developer',
            'job_description' => 'Build APIs',
            'public_url' => 'public-interview-token',
            'link_expires_at' => now()->addDay(),
            'status' => 'draft',
            'user_id' => $user->id,
        ]);

        $this->postJson("/join/{$interview->public_url}/live-token")
            ->assertOk()
            ->assertJsonPath('token', 'auth_tokens/recruitment-token')
            ->assertJsonPath('liveModel', 'gemini-3.1-flash-live-preview');
    }

    public function test_transcription_fallback_log_endpoint_accepts_reason_without_secrets(): void
    {
        $this->postJson('/live/transcription-fallback', [
            'context' => 'loan',
            'reason' => 'transcribe_connect_failed',
        ])->assertOk()
            ->assertJson(['logged' => true]);
    }
}
