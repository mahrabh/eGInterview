<?php

namespace Tests\Feature;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplicant;
use App\Models\BankOpeningApplication;
use App\Models\User;
use App\Support\BankOpening\AccountProductCatalog;
use App\Support\BankOpening\AmountNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankOpeningInterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_only_collects_name_and_phone_then_opens_live_interview_shell(): void
    {
        [$token] = $this->invite();

        $this->get(route('bank-opening.public', $token))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="phone"', false)
            ->assertDontSee('name="account_type"', false);

        $this->post(route('bank-opening.information', $token), [
            'name' => 'Karim Hossain',
            'phone' => '01715551234',
        ])->assertRedirect(route('bank-opening.public', $token));

        $this->get(route('bank-opening.public', $token))
            ->assertOk()
            ->assertSee('bank-opening-interview-root', false)
            ->assertSee('bank-opening-interview', false);
    }

    public function test_ucb_greeting_uses_applicant_name_and_brand(): void
    {
        [$token] = $this->readyForInterview();

        $state = $this->postJson(route('bank-opening.interview.language', $token), [
            'language' => 'en',
        ])->assertOk()->json();

        $this->assertStringContainsString('Welcome, Karim Hossain, to UCB Bank', $state['greeting']);
        $this->assertStringNotContainsString('NRB Bank', $state['greeting']);
        $this->assertSame('UCB', $state['bank_code']);
        $this->assertSame('ucb-retail-v1', $state['catalog_version']);

        $bn = $this->postJson(route('bank-opening.interview.language', $token), [
            'language' => 'bn',
        ])->assertOk()->json();

        $this->assertStringContainsString('Karim Hossain', $bn['greeting']);
        $this->assertStringContainsString('UCB Bank', $bn['greeting']);
    }

    public function test_language_then_account_requires_explicit_confirmation(): void
    {
        [$token, $application, $analyst] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.select', $token), [
            'account_type' => 'savings',
        ])->assertStatus(422);

        $this->postJson(route('bank-opening.interview.language', $token), [
            'language' => 'en',
        ])->assertOk()->assertJsonPath('interview_state', 'awaiting_account_selection');

        $matches = $this->postJson(route('bank-opening.interview.match', $token), [
            'hint' => 'savings account',
        ])->assertOk()->json('matches');

        $this->assertIsArray($matches);
        $this->assertGreaterThan(1, count($matches), 'Generic savings must not collapse to one subtype');
        $this->assertNull($application->fresh()->account_type);

        $this->postJson(route('bank-opening.interview.select', $token), [
            'account_type' => 'fake-product',
        ])->assertStatus(422);

        $this->postJson(route('bank-opening.interview.select', $token), [
            'account_type' => 'dynamic_benefits',
        ])->assertOk()
            ->assertJsonPath('account_type', 'dynamic_benefits')
            ->assertJsonPath('interview_state', 'awaiting_account_confirmation')
            ->assertJsonPath('account_confirmed', false);

        $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application))
            ->assertOk()
            ->assertSee('Dynamic Benefits Savings Account');

        $this->postJson(route('bank-opening.interview.confirm', $token), [
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('account_confirmed', true)
            ->assertJsonPath(
                'planned_questions.0.key',
                AccountProductCatalog::questionsForProfile('standard_savings')[0]['key']
            );

        $this->assertSame(
            'awaiting_answer',
            $this->getJson(route('bank-opening.interview.bootstrap', $token))->json('interview_state')
        );
    }

    public function test_ucb_nrb_savings_is_available_and_not_confused_with_nrb_bank(): void
    {
        [$token] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'en'])->assertOk();

        $this->postJson(route('bank-opening.interview.select', $token), [
            'account_type' => 'ucb_nrb_savings',
        ])->assertOk()
            ->assertJsonPath('account_type', 'ucb_nrb_savings')
            ->assertJsonPath('account_label', 'UCB NRB Savings');

        $slugs = collect(AccountProductCatalog::enabledProducts())->pluck('slug')->all();
        $this->assertContains('ucb_nrb_savings', $slugs);
        $this->assertNotContains('nrb-my-saving', $slugs);
    }

    public function test_saying_savings_does_not_auto_select_dynamic_benefits(): void
    {
        [$token, $application] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'en'])->assertOk();

        $matches = $this->postJson(route('bank-opening.interview.match', $token), [
            'hint' => 'I want to open saving account',
        ])->assertOk()->json('matches');

        $this->assertIsArray($matches);
        $this->assertNotSame(['dynamic_benefits'], $matches);
        $this->assertNull($application->fresh()->account_type);
        $this->assertFalse((bool) $application->fresh()->account_type_confirmed);
    }

    public function test_planned_questions_are_four_and_opening_monthly_are_separate(): void
    {
        [$token] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'bn'])->assertOk();
        $this->postJson(route('bank-opening.interview.select', $token), [
            'account_type' => 'savings',
        ])->assertOk();

        $state = $this->postJson(route('bank-opening.interview.confirm', $token), [
            'confirmed' => true,
        ])->assertOk()->json();

        $this->assertCount(4, $state['planned_questions']);
        $keys = array_column($state['planned_questions'], 'key');
        $this->assertSame(['profession', 'source_of_funds', 'opening_deposit', 'expected_monthly_deposit'], $keys);
    }

    public function test_incomplete_fragments_rejected_and_amounts_stored_separately(): void
    {
        [$token, $application] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'en'])->assertOk();
        $this->postJson(route('bank-opening.interview.select', $token), ['account_type' => 'savings'])->assertOk();
        $this->postJson(route('bank-opening.interview.confirm', $token), ['confirmed' => true])->assertOk();

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'Hello',
        ])->assertStatus(422);

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'Software engineer',
            'client_turn_id' => 'turn-job-1',
        ])->assertOk();

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'Software engineer again',
            'client_turn_id' => 'turn-job-1',
        ])->assertOk();

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'Salary from employment',
            'client_turn_id' => 'turn-funds-1',
        ])->assertOk();

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => '50',
            'client_turn_id' => 'turn-open-bad',
        ])->assertStatus(422);

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'I will deposit BDT 5,000 when opening',
            'answer_raw' => 'খোলার সময় 5000 জমা করব',
            'client_turn_id' => 'turn-open-1',
        ])->assertOk();

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'I will deposit BDT 10,000 every month',
            'client_turn_id' => 'turn-month-1',
        ])->assertOk()->assertJsonPath('interview_state', 'reviewing_summary');

        $application->refresh();
        $answers = $application->structured_answers;
        $this->assertSame(5000, $answers['opening_deposit']['normalized']['amount']);
        $this->assertSame(10000, $answers['expected_monthly_deposit']['normalized']['amount']);
        $this->assertNull($application->interview_completed_at);

        $this->postJson(route('bank-opening.interview.confirm-summary', $token), [
            'confirmed' => false,
            'correct_key' => 'opening_deposit',
        ])->assertOk()->assertJsonPath('interview_state', 'awaiting_answer');

        $this->postJson(route('bank-opening.interview.answer', $token), [
            'answer' => 'Opening deposit পাঁচ হাজার',
            'client_turn_id' => 'turn-open-2',
        ])->assertOk();

        $done = $this->postJson(route('bank-opening.interview.confirm-summary', $token), [
            'confirmed' => true,
        ])->assertOk()
            ->assertJsonPath('interview_state', 'completed')
            ->json();

        $this->assertStringContainsString('Karim Hossain', $done['completion_message']);
        $this->assertStringContainsString('documents', strtolower($done['completion_message']));

        $application->refresh();
        $this->assertNotNull($application->interview_completed_at);
        $this->assertSame(BankOpeningStage::DocumentsPending, $application->stage);
        $this->assertSame('UCB', $application->bank_code);
        $this->assertSame('ucb-retail-v1', $application->catalog_version);
        $this->assertSame('Savings Account', $application->account_type_name_snapshot);
        $this->assertNotNull($application->confirmed_summary);
    }

    public function test_live_transcript_save_does_not_guess_account_or_complete(): void
    {
        [$token, $application, $analyst] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'en'])->assertOk();

        $transcript = "AI: Welcome.\n\nApplicant: I want savings.\n\nAI: Just to confirm, Current Account, correct?";

        $this->postJson(route('bank-opening.transcript', $token), [
            'transcript_text' => $transcript,
        ])->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $this->assertSame($transcript, $application->transcript_text);
        $this->assertNull($application->account_type);
        $this->assertNull($application->interview_completed_at);
        $this->assertNotSame(BankOpeningStage::DocumentsPending, $application->stage);

        $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application))
            ->assertOk()
            ->assertDontSee('AI Approval');
    }

    public function test_complete_live_session_opens_documents_without_guessing_account_type(): void
    {
        [$token, $application] = $this->readyForInterview();

        $transcript = "AI: Hello Karim Sir.\n\nApplicant: Yes ready.\n\n"
            ."AI: What account type would you like?\n\n"
            ."Applicant: I want a Savings Account\n\n"
            ."AI: Just to confirm, Savings Account, correct?\n\n"
            ."Applicant: Yes\n\n"
            ."AI: What is your profession?\n\nApplicant: Teacher\n\n"
            .'AI: Thank you for your time and for providing the required information. Please click the End Session button to complete the interview.';

        $payload = $this->postJson(route('bank-opening.interview.complete-live', $token), [
            'transcript_text' => $transcript,
        ])
            ->assertOk()
            ->assertJsonPath('interview_state', 'completed')
            ->assertJsonPath('needs_account_type_selection', true)
            ->assertJsonPath('documents_window_hours', 24)
            ->json();

        $this->assertNull($payload['account_type']);
        $this->assertNotEmpty($payload['account_type_options']);

        $application->refresh();
        $this->assertSame($transcript, $application->transcript_text);
        $this->assertNull($application->account_type);
        $this->assertFalse((bool) $application->account_type_confirmed);
        $this->assertNotNull($application->interview_completed_at);
        $this->assertSame(BankOpeningStage::DocumentsPending, $application->stage);
        $this->assertTrue($application->public_token_expiry?->greaterThan(now()->addHours(23)));
    }

    public function test_documents_account_type_selection_unlocks_requirements(): void
    {
        Storage::fake('local');
        [$token, $application] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.complete-live', $token), [
            'transcript_text' => "AI: Hello.\n\nApplicant: Ready.",
        ])->assertOk();

        $this->postJson(route('bank-opening.documents.account-type', $token), [
            'account_type' => 'savings',
        ])
            ->assertOk()
            ->assertJsonPath('account_type', 'savings')
            ->assertJsonPath('account_confirmed', true)
            ->assertJsonPath('needs_account_type_selection', false);

        $application->refresh();
        $this->assertSame('savings', $application->account_type);
        $this->assertTrue((bool) $application->account_type_confirmed);
        $this->assertSame('Savings Account', $application->account_type_name_snapshot);
        $this->assertSame('Savings Account', $application->confirmed_summary['account_type_name'] ?? null);

        $this->post(route('bank-opening.documents.store', $token), [
            'document_type' => 'applicant_photo_id',
            'file' => UploadedFile::fake()->image('nid.jpg'),
        ])->assertOk();

        $this->postJson(route('bank-opening.submit', $token))
            ->assertOk()
            ->assertJsonPath('application_submitted', true);
    }

    public function test_officer_show_prefers_live_account_label_over_stale_summary(): void
    {
        [$token, $application, $analyst] = $this->readyForInterview();

        $this->postJson(route('bank-opening.interview.complete-live', $token), [
            'transcript_text' => "AI: Hello.\n\nApplicant: Ready.",
        ])->assertOk();

        // Simulate a stale live summary that wrongly captured RFCD.
        $application->forceFill([
            'confirmed_summary' => [
                'account_type_slug' => 'rfcd',
                'account_type_name' => 'RFCD',
                'language' => 'en',
                'source' => 'live_session',
            ],
        ])->save();

        $this->postJson(route('bank-opening.documents.account-type', $token), [
            'account_type' => 'savings',
        ])->assertOk();

        $html = $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application->fresh()))
            ->assertOk()
            ->assertSee('Savings Account')
            ->getContent();

        // Interview summary must use the live account label, not the stale RFCD summary.
        $this->assertStringContainsString('Interview summary', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/Interview summary[\s\S]{0,400}Account[\s\S]{0,80}RFCD/i',
            $html
        );
    }

    public function test_amount_normalizer_supports_bengali_and_english(): void
    {
        $this->assertSame(5000, AmountNormalizer::parse('পাঁচ হাজার')['amount']);
        $this->assertSame(10000, AmountNormalizer::parse('দশ হাজার')['amount']);
        $this->assertSame(10000, AmountNormalizer::parse('BDT 10,000')['amount']);
        $this->assertSame(100000, AmountNormalizer::parse('one lakh')['amount']);
    }

    public function test_documents_upload_after_confirmed_interview(): void
    {
        Storage::fake('local');
        [$token, $application, $analyst] = $this->readyForInterview();

        $this->completeInterviewThroughSummary($token);

        $file = UploadedFile::fake()->image('nid.jpg');

        $this->post(route('bank-opening.documents.store', $token), [
            'document_type' => 'applicant_photo_id',
            'file' => $file,
        ])->assertOk()->assertJsonPath('success', true);

        $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application->fresh(['documents'])))
            ->assertOk()
            ->assertSee('Applicant photo ID')
            ->assertSee('nid.jpg');
    }

    public function test_submit_application_requires_all_documents_then_marks_submitted(): void
    {
        Storage::fake('local');
        [$token, $application] = $this->readyForInterview();

        $this->completeInterviewThroughSummary($token);

        $this->postJson(route('bank-opening.submit', $token))->assertStatus(422);

        $this->post(route('bank-opening.documents.store', $token), [
            'document_type' => 'applicant_photo_id',
            'file' => UploadedFile::fake()->image('applicant_photo_id.jpg'),
        ])->assertOk();

        $this->postJson(route('bank-opening.submit', $token))
            ->assertOk()
            ->assertJsonPath('application_submitted', true);

        $application->refresh();
        $this->assertSame(BankOpeningStage::Submitted, $application->stage);
        $this->assertNotNull($application->submitted_at);
        $this->assertNotNull($application->documents_submitted_at);
    }

    public function test_bank_opening_live_token_endpoint(): void
    {
        config([
            'services.gemini.key' => 'server-only-key',
            'services.gemini.live_model' => 'gemini-3.1-flash-live-preview',
            'services.gemini.transcription_model' => 'gemini-3.5-transcribe-live',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/v1beta/auth_tokens' => Http::response([
                'name' => 'auth_tokens/bank-token',
                'expireTime' => now()->addMinutes(30)->toIso8601String(),
            ], 200),
        ]);

        [$token] = $this->readyForInterview();

        $this->postJson("/bank-opening/{$token}/live-token")
            ->assertOk()
            ->assertJsonPath('token', 'auth_tokens/bank-token')
            ->assertJsonPath('context', 'bank_opening');
    }

    public function test_loan_and_recruitment_routes_untouched(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $recruiter = User::factory()->create(['role' => 'recruiter']);

        $this->actingAs($analyst)->get('/loan-applications')->assertOk();
        $this->actingAs($recruiter)->get('/recruitment')->assertOk();
        $this->actingAs($recruiter)->get('/bank-openings')->assertForbidden();
    }

    private function completeInterviewThroughSummary(string $token): void
    {
        $this->postJson(route('bank-opening.interview.language', $token), ['language' => 'en'])->assertOk();
        $this->postJson(route('bank-opening.interview.select', $token), ['account_type' => 'savings'])->assertOk();
        $this->postJson(route('bank-opening.interview.confirm', $token), ['confirmed' => true])->assertOk();

        $answers = [
            'Teacher',
            'Salary',
            'Opening deposit 2000 taka',
            'Monthly deposit 5000 taka',
        ];

        foreach ($answers as $i => $answer) {
            $this->postJson(route('bank-opening.interview.answer', $token), [
                'answer' => $answer,
                'client_turn_id' => 'complete-'.$i,
            ])->assertOk();
        }

        $this->postJson(route('bank-opening.interview.confirm-summary', $token), [
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('interview_state', 'completed');
    }

    /**
     * @return array{0: string, 1: BankOpeningApplication, 2: User}
     */
    private function invite(?User $analyst = null): array
    {
        $analyst ??= User::factory()->create(['role' => 'analyst']);

        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-TEST'.random_int(1000, 9999),
            'created_by' => $analyst->id,
        ]);

        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'stage' => BankOpeningStage::Invited,
            'documents_required' => 3,
        ]);

        $token = $application->issuePublicToken();

        return [$token, $application->fresh(), $analyst];
    }

    /**
     * @return array{0: string, 1: BankOpeningApplication, 2: User}
     */
    private function readyForInterview(?User $analyst = null): array
    {
        [$token, $application, $analyst] = $this->invite($analyst);

        $application->applicant->forceFill([
            'name' => 'Karim Hossain',
            'phone' => '01715551234',
            'phone_masked' => '017****1234',
        ])->save();

        $application->information_submitted_at = now();
        $application->stage = BankOpeningStage::InformationSubmitted;
        $application->save();

        return [$token, $application->fresh(), $analyst];
    }
}
