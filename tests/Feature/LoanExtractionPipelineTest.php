<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use App\Models\User;
use App\Services\LoanExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoanExtractionPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected LoanApplicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.gemini.key', 'test-gemini-key');
        Config::set('services.gemini.extraction_model', 'gemini-3.7-flash');

        $this->user = User::factory()->create(['role' => 'analyst']);
        $this->applicant = LoanApplicant::create([
            'name' => 'Pipeline Applicant',
            'phone' => '01710000001',
            'application_reference' => 'LA-PIPE',
            'created_by' => $this->user->id,
        ]);
    }

    /** @param array<string, mixed> $fields */
    private function fakeGeminiExtraction(array $fields, int $status = 200, ?string $bodyText = null): void
    {
        if ($bodyText !== null) {
            Http::fake([
                'generativelanguage.googleapis.com/*' => Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => $bodyText]]],
                        'finishReason' => 'STOP',
                    ]],
                ], $status),
            ]);

            return;
        }

        $payload = array_merge([
            'missing_fields' => [],
            'contradictions' => [],
            'needs_confirmation' => false,
        ], $fields);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($payload)]]],
                    'finishReason' => 'STOP',
                ]],
            ], $status),
        ]);
    }

    /** @return array{value: mixed, evidence: string, confidence: float|int} */
    private function field(mixed $value, string $evidence = 'Applicant stated clearly.', int $confidence = 95): array
    {
        return [
            'value' => $value,
            'evidence' => $evidence,
            'confidence' => $confidence,
        ];
    }

    /** @return array<string, mixed> */
    private function completePersonalPayload(): array
    {
        return [
            'loan_type' => $this->field('personal'),
            'loan_purpose' => $this->field('Medical expenses'),
            'requested_amount' => $this->field(500000),
            'requested_tenure' => $this->field(36),
            'exact_monthly_income' => $this->field(80000),
            'income_source' => $this->field('Salaried employment'),
            'employer_name' => $this->field('ABC Ltd'),
            'other_regular_monthly_income' => $this->field(0),
            'existing_monthly_obligations' => $this->field(5000),
            'asset_value' => $this->field(null),
            'down_payment' => $this->field(null),
        ];
    }

    public function test_successful_extraction_triggers_assessment(): void
    {
        $application = $this->createSubmittedApplication(
            'AI: Loan type? Applicant: Personal loan for medical bills. AI: Amount? Applicant: Five lakh. AI: Tenure? Applicant: 36 months. AI: Income? Applicant: Eighty thousand from ABC Ltd salary. AI: Other income? Applicant: None. AI: Existing EMI? Applicant: Five thousand.'
        );

        $this->fakeGeminiExtraction($this->completePersonalPayload());

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $fresh = $application->fresh();
        $this->assertTrue($result);
        $this->assertSame('assessed', $fresh->status);
        $this->assertSame('Indicatively Eligible', $fresh->outcome);
        $this->assertSame('personal', $fresh->loan_type);
        $this->assertSame('80000.00', (string) $fresh->monthly_income);
        $this->assertSame('ABC Ltd', $fresh->employer_name);
        $this->assertSame('Salaried employment', $fresh->income_source);
        $this->assertSame('0.00', (string) $fresh->other_monthly_income);
        $this->assertSame('5000.00', (string) $fresh->existing_emi);
        $this->assertSame(36, $fresh->tenure_months);
        $this->assertIsArray($fresh->extracted_data);
        $this->assertSame('Medical expenses', $fresh->extracted_data['loan_purpose']['value'] ?? null);
    }

    public function test_partial_extraction_persists_known_values(): void
    {
        $application = $this->createSubmittedApplication('Applicant: I need a car loan but amount is unclear.');

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('car'),
            'loan_purpose' => $this->field('Vehicle purchase'),
            'requested_amount' => $this->field(null, '', 40),
            'requested_tenure' => $this->field(60),
            'exact_monthly_income' => $this->field(120000),
            'income_source' => $this->field('Business'),
            'employer_name' => $this->field('Own shop'),
            'other_regular_monthly_income' => $this->field(10000),
            'existing_monthly_obligations' => $this->field(0),
            'asset_value' => $this->field(null, '', 30),
            'down_payment' => $this->field(null, '', 30),
            'missing_fields' => ['requested_amount', 'asset_value', 'down_payment'],
        ]);

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $fresh = $application->fresh();
        $this->assertTrue($result);
        $this->assertContains($fresh->status, ['assessed', 'needs_review']);
        $this->assertNotNull($fresh->outcome);
        $this->assertSame('car', $fresh->loan_type);
        $this->assertSame('120000.00', (string) $fresh->monthly_income);
        $this->assertSame('Own shop', $fresh->employer_name);
        $this->assertSame('10000.00', (string) $fresh->other_monthly_income);
        $this->assertNull($fresh->extracted_data['requested_amount']['value'] ?? null);
    }

    public function test_missing_transcript_marks_needs_review_without_persisting_extraction(): void
    {
        $application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'draft',
            'submitted_at' => now(),
        ]);

        Http::fake();

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $fresh = $application->fresh();
        $this->assertFalse($result);
        $this->assertSame('needs_review', $fresh->status);
        $this->assertNull($fresh->extracted_data);
        Http::assertNothingSent();
        $this->assertDatabaseHas('loan_application_events', [
            'loan_application_id' => $fresh->id,
            'event_type' => 'extraction_missing_transcript',
        ]);
    }

    public function test_gemini_http_failure_leaves_transcript_intact(): void
    {
        $application = $this->createSubmittedApplication('Applicant: Personal loan fifty thousand.');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Bad request'],
            ], 400),
        ]);

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $fresh = $application->fresh();
        $this->assertFalse($result);
        $this->assertSame('needs_review', $fresh->status);
        $this->assertNotEmpty($fresh->transcript);
        $this->assertNull($fresh->extracted_data);
        $this->assertDatabaseHas('loan_application_events', [
            'loan_application_id' => $fresh->id,
            'event_type' => 'extraction_failed',
        ]);
    }

    public function test_gemini_json_failure_is_logged_safely_and_non_destructive(): void
    {
        $application = $this->createSubmittedApplication('Applicant: Personal loan fifty thousand.');

        $this->fakeGeminiExtraction([], 200, 'not-json');

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $fresh = $application->fresh();
        $this->assertFalse($result);
        $this->assertSame('needs_review', $fresh->status);
        $this->assertNull($fresh->extracted_data);
        $this->assertNotEmpty($fresh->transcript);
    }

    public function test_transcript_text_column_is_used_when_transcript_column_is_empty(): void
    {
        $application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'processing',
            'submitted_at' => now(),
            'transcript_text' => 'Applicant: Personal loan for medical bills, amount five lakh.',
        ]);

        $this->fakeGeminiExtraction($this->completePersonalPayload());

        $service = new LoanExtractionService();
        $result = $service->extract($application);

        $this->assertTrue($result);
        $this->assertSame('assessed', $application->fresh()->status);
    }

    public function test_transcript_save_runs_extraction_after_commit(): void
    {
        $application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'draft',
            'public_token_hash' => hash('sha256', 'commit-token'),
            'public_token_expiry' => now()->addDay(),
        ]);

        $this->fakeGeminiExtraction($this->completePersonalPayload());

        $response = $this->postJson('/loan-interview/commit-token/transcript', [
            'transcript_text' => 'Applicant: Personal loan five lakh for medical bills.',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $fresh = $application->fresh();
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame('Applicant: Personal loan five lakh for medical bills.', $fresh->transcript);
        $this->assertSame('Applicant: Personal loan five lakh for medical bills.', $fresh->transcript_text);
        $this->assertSame('assessed', $fresh->status);
        $this->assertSame('Indicatively Eligible', $fresh->outcome);
        $this->assertNotNull($fresh->extracted_data);
    }

    public function test_retry_processing_is_idempotent_and_preserves_transcript(): void
    {
        $application = $this->createSubmittedApplication('Applicant: Personal loan five lakh.');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary failure']], 503)
                ->push(['error' => ['message' => 'Temporary failure']], 503)
                ->push(['error' => ['message' => 'Temporary failure']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => json_encode(array_merge(
                            $this->completePersonalPayload(),
                            ['missing_fields' => [], 'contradictions' => [], 'needs_confirmation' => false]
                        ))]]],
                        'finishReason' => 'STOP',
                    ]],
                ], 200),
        ]);

        $service = new LoanExtractionService();
        $this->assertFalse($service->extract($application));

        $afterFailure = $application->fresh();
        $originalTranscript = $afterFailure->transcript;
        $this->assertSame('needs_review', $afterFailure->status);
        $this->assertNull($afterFailure->extracted_data);

        $this->actingAs($this->user)
            ->post("/loan-applications/{$application->id}/retry-extraction")
            ->assertRedirect();

        $afterRetry = $application->fresh();
        $this->assertSame($originalTranscript, $afterRetry->transcript);
        $this->assertSame('assessed', $afterRetry->status);
        $this->assertSame('Indicatively Eligible', $afterRetry->outcome);
        $this->assertIsArray($afterRetry->extracted_data);
        $this->assertIsArray($afterRetry->calculation_data);
    }

    public function test_report_shows_populated_extracted_fields(): void
    {
        $application = $this->createSubmittedApplication('Applicant: Personal loan five lakh.');
        $this->fakeGeminiExtraction($this->completePersonalPayload());

        (new LoanExtractionService())->extract($application);

        $response = $this->actingAs($this->user)
            ->get("/loan-applications/{$application->id}/report");

        $response->assertOk();
        $response->assertSee('Approved');
        $response->assertSee('Medical expenses');
        $response->assertSee('value="personal"', false);
        $response->assertSee('value="500000"', false);
    }

    public function test_report_shows_reextract_button_for_needs_review_with_missing_fields(): void
    {
        $application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'needs_review',
            'submitted_at' => now(),
            'transcript' => 'Applicant: I earn two lakh per month.',
            'transcript_text' => 'Applicant: I earn two lakh per month.',
            'loan_type' => 'home',
            'purpose' => 'Build a house',
            'tenure_months' => 240,
            'asset_value' => 15000000,
            'down_payment' => 5000000,
            'extracted_data' => [
                'loan_type' => ['value' => 'home', 'evidence' => 'home', 'confidence' => 95],
                'loan_purpose' => ['value' => 'Build a house', 'evidence' => 'house', 'confidence' => 95],
                'requested_amount' => ['value' => null, 'evidence' => '', 'confidence' => 0],
                'requested_tenure' => ['value' => 240, 'evidence' => '240 months', 'confidence' => 95],
                'exact_monthly_income' => ['value' => null, 'evidence' => '', 'confidence' => 0],
                'income_source' => ['value' => 'business', 'evidence' => 'business', 'confidence' => 95],
                'employer_name' => ['value' => 'Tanmoy Unit Trade', 'evidence' => 'Tanmoy Unit Trade', 'confidence' => 95],
                'other_regular_monthly_income' => ['value' => 0, 'evidence' => 'none', 'confidence' => 95],
                'existing_monthly_obligations' => ['value' => 0, 'evidence' => 'none', 'confidence' => 95],
                'asset_value' => ['value' => 15000000, 'evidence' => '1.5 crore', 'confidence' => 95],
                'down_payment' => ['value' => 5000000, 'evidence' => '50 lakh', 'confidence' => 95],
                'missing_fields' => ['requested_amount', 'exact_monthly_income'],
                '_meta' => ['blocking_fields' => ['requested_amount', 'exact_monthly_income']],
            ],
            'reason_codes' => [
                'Monthly income is missing or invalid.',
                'Requested loan amount is missing or invalid.',
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->get("/loan-applications/{$application->id}/report");

        $response->assertOk();
        $response->assertSee('Needs Review');
        $response->assertSee('Re-extract');
        $response->assertSee('Re-extract from Transcript');
        $response->assertSee('Missing fields detected:');
        $response->assertSee('Requested Amount');
        $response->assertSee('Monthly Income');
    }

    public function test_report_status_endpoint_returns_readiness(): void
    {
        $application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'processing',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson("/loan-applications/{$application->id}/status")
            ->assertOk()
            ->assertJson([
                'status' => 'processing',
                'is_report_ready' => false,
                'has_extracted_data' => false,
            ]);
    }

    public function test_status_snapshot_returns_visible_application_states(): void
    {
        $draftWithLink = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'draft',
            'public_token_hash' => hash('sha256', 'draft-token'),
            'public_token_expiry' => now()->addDay(),
        ]);

        $processing = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'processing',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson('/loan-applications/status-snapshot?ids='.$draftWithLink->id.','.$processing->id)
            ->assertOk()
            ->assertJsonPath('applications.'.$draftWithLink->id.'.status', 'draft')
            ->assertJsonPath('applications.'.$processing->id.'.status', 'processing')
            ->assertJsonPath('applications.'.$processing->id.'.is_report_ready', false);
    }

    public function test_missing_gemini_key_does_not_call_provider_or_persist_extraction(): void
    {
        Config::set('services.gemini.key', '');

        $application = $this->createSubmittedApplication('Applicant: Personal loan five lakh.');
        Http::fake();

        $service = new LoanExtractionService();
        $this->assertFalse($service->extract($application));

        Http::assertNothingSent();
        $fresh = $application->fresh();
        $this->assertNull($fresh->extracted_data);
        $this->assertNotEmpty($fresh->transcript);
    }

    public function test_transcript_supplement_fills_missing_gemini_fields(): void
    {
        $transcript = implode("\n", [
            'AI: What type of loan do you need?',
            'Candidate: Car loan for a new vehicle.',
            'AI: What is the purpose of the loan?',
            'Candidate: To buy a new car for family use.',
            'AI: How much loan amount do you want to borrow?',
            'Candidate: Ten lakh taka.',
            'AI: For how many months?',
            'Candidate: 36 months.',
            'AI: What is your monthly income?',
            'Candidate: Thirty thousand taka.',
            'AI: Any other monthly income?',
            'Candidate: None.',
            'AI: Existing monthly EMI?',
            'Candidate: Five thousand.',
            'AI: What is the vehicle price?',
            'Candidate: Twenty lakh.',
            'AI: How much down payment can you provide?',
            'Candidate: Five lakh.',
        ]);

        $application = $this->createSubmittedApplication($transcript);

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('car'),
            'loan_purpose' => $this->field(null, '', 30),
            'requested_amount' => $this->field(null, '', 30),
            'requested_tenure' => $this->field(36),
            'exact_monthly_income' => $this->field(30000),
            'income_source' => $this->field('job'),
            'employer_name' => $this->field('eGeneration PLC'),
            'other_regular_monthly_income' => $this->field(0),
            'existing_monthly_obligations' => $this->field(5000),
            'asset_value' => $this->field(null, '', 30),
            'down_payment' => $this->field(null, '', 30),
            'missing_fields' => ['loan_purpose', 'requested_amount', 'asset_value', 'down_payment'],
        ]);

        $service = new LoanExtractionService();
        $this->assertTrue($service->extract($application));

        $fresh = $application->fresh();
        $this->assertSame('To buy a new car for family use.', $fresh->extracted_data['loan_purpose']['value'] ?? null);
        $this->assertSame(1000000.0, (float) ($fresh->extracted_data['requested_amount']['value'] ?? 0));
        $this->assertSame(2000000.0, (float) ($fresh->extracted_data['asset_value']['value'] ?? 0));
        $this->assertSame(500000.0, (float) ($fresh->extracted_data['down_payment']['value'] ?? 0));
        $this->assertSame('1000000.00', (string) $fresh->requested_amount);
        $this->assertIsArray($fresh->calculation_data);
        $this->assertArrayHasKey('rule_snapshot', $fresh->calculation_data);
    }

    public function test_bengali_home_loan_transcript_supplements_income_and_requested_amount(): void
    {
        $transcript = implode("\n", [
            'AI: Hello tanmoy, I am a professional Bank Loan Agent AI. Are you ready to begin?',
            'Applicant: Yes, I am ready, but can we speak in Bengali?',
            'AI: অবশ্যই।',
            'AI: আপনি কি ধরণের লোন নিতে চান - ব্যক্তিগত, গাড়ি নাকি বাড়ির জন্য?',
            'Applicant: বাড়ির জন্য',
            'AI: ঠিক আছে। আপনি এই লোনটি কেন নিতে চান, কারণটি বলুন।',
            'Applicant: আমি লোনটি নিতে চাই, একটি বাড়ি করবো',
            'AI: বুঝতে পেরেছি।',
            'AI: আপনার বাড়ির জন্য মোট কত টাকা লোনের প্রয়োজন? নির্দিষ্ট অংকটি বলুন।',
            'AI: ধন্যবাদ। এই লোনটি পরিশোধ করার জন্য আপনি কত সময় নিতে চান? অনুগ্রহ করে মাসের সংখ্যাটি বলুন।',
            'Applicant: 240 মাস নিতে চাই',
            'Applicant: আমি একটা ব্যবসা করি যেখান থেকে আমি দুই লাখ টাকা প্লাস আর্নিং করি',
            'AI: হ্যাঁ, ২৪০ মাস মানে ২০ বছর। আপনার আয়ের উৎস কি?',
            'AI: আপনি কি চাকরি করেন, নাকি ব্যবসা?',
            'AI: আপনার ব্যবসার নাম কি? এবং আপনার মাসিক নিট আয় কত? নির্দিষ্ট অংকটি বলুন।',
            'Applicant: আমার ব্যবসার নাম হচ্ছে তন্ময় ইউনিট ট্রেড আমি মাসে দুই লাখ টাকা প্লাস আর্ন করি',
            'AI: আপনার কি অন্য কোনো নিয়মিত মাসিক আয় আছে? থাকলে পরিমাণটি বলুন। না থাকলে নেই বলুন।',
            'Applicant: আমার অন্য কোনো আয় নেই',
            'AI: বর্তমানে আপনার কোনো মাসিক কিস্তি বা ঋণের বাধ্যবাধকতা আছে কি? থাকলে পরিমাণটি বলুন। না থাকলে নেই বলুন।',
            'Applicant: না না কোনো বাধ্যবাধকতা আমার নেই',
            'AI: আপনি যে বাড়িটি তৈরি করতে চান, সেটির আনুমানিক মূল্য কত? এবং ডাউন পেমেন্ট হিসেবে আপনি কত টাকা দিতে পারবেন?',
            'Applicant: আমার বাড়ির মূল্য হচ্ছে দেড় কোটি টাকা আনুমানিক আমি কন্ট্রিবিউট করতে পারবো ডাউন পেমেন্ট হিসেবে 50 লক্ষ টাকা',
        ]);

        $application = $this->createSubmittedApplication($transcript);

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('home'),
            'loan_purpose' => $this->field('একটি বাড়ি করবো'),
            'requested_amount' => $this->field(null, '', 30),
            'requested_tenure' => $this->field(240),
            'exact_monthly_income' => $this->field(null, '', 30),
            'income_source' => $this->field('ব্যবসা'),
            'employer_name' => $this->field('তন্ময় ইউনিট ট্রেড'),
            'other_regular_monthly_income' => $this->field(0),
            'existing_monthly_obligations' => $this->field(0),
            'asset_value' => $this->field(15000000),
            'down_payment' => $this->field(5000000),
            'missing_fields' => ['requested_amount', 'exact_monthly_income'],
        ]);

        $service = new LoanExtractionService();
        $this->assertTrue($service->extract($application));

        $fresh = $application->fresh();
        $this->assertSame(200000.0, (float) ($fresh->extracted_data['exact_monthly_income']['value'] ?? 0));
        $this->assertSame(10000000.0, (float) ($fresh->extracted_data['requested_amount']['value'] ?? 0));
        $this->assertSame('200000.00', (string) $fresh->monthly_income);
        $this->assertSame('10000000.00', (string) $fresh->requested_amount);
    }

    public function test_income_amount_is_not_used_as_requested_tenure(): void
    {
        $transcript = implode("\n", [
            'AI: আপনি এই লোনটি কত মাসের জন্য নিতে চান? অনুগ্রহ করে সঠিক মাস সংখ্যাটি বলুন।',
            'AI: ঠিক আছে। আপনার আয়ের উৎস কি?',
            'Applicant: আমি চাকরি করি',
            'AI: এবার বলুন, আপনার মাসিক নিট আয় কত?',
            'Applicant: আমার মাসিক আয় 90,000 টাকা',
        ]);

        $application = $this->createSubmittedApplication($transcript);

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('personal'),
            'loan_purpose' => $this->field('জমি কেনার জন্য'),
            'requested_amount' => $this->field(200000),
            'requested_tenure' => $this->field(90),
            'exact_monthly_income' => $this->field(90000),
            'income_source' => $this->field('চাকরি'),
            'employer_name' => $this->field('eGeneration PLC'),
            'other_regular_monthly_income' => $this->field(0),
            'existing_monthly_obligations' => $this->field(0),
            'asset_value' => $this->field(null),
            'down_payment' => $this->field(null),
        ]);

        $service = new LoanExtractionService();
        $this->assertTrue($service->extract($application));

        $fresh = $application->fresh();
        $this->assertNull($fresh->extracted_data['requested_tenure']['value'] ?? null);
    }

    public function test_applicant_tenure_turn_overrides_wrong_gemini_tenure(): void
    {
        $transcript = implode("\n", [
            'AI: আপনি এই লোনটি কত মাসের জন্য নিতে চান? অনুগ্রহ করে সঠিক মাস সংখ্যাটি বলুন।',
            'Applicant: 24 মাস নিতে চাই',
            'AI: ঠিক আছে। আপনার আয়ের উৎস কি?',
            'Applicant: আমি চাকরি করি',
            'AI: এবার বলুন, আপনার মাসিক নিট আয় কত?',
            'Applicant: আমার মাসিক আয় 90,000 টাকা',
        ]);

        $application = $this->createSubmittedApplication($transcript);

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('personal'),
            'loan_purpose' => $this->field('জমি কেনার জন্য'),
            'requested_amount' => $this->field(200000),
            'requested_tenure' => $this->field(90),
            'exact_monthly_income' => $this->field(90000),
            'income_source' => $this->field('চাকরি'),
            'employer_name' => $this->field('eGeneration PLC'),
            'other_regular_monthly_income' => $this->field(0),
            'existing_monthly_obligations' => $this->field(0),
            'asset_value' => $this->field(null),
            'down_payment' => $this->field(null),
        ]);

        $service = new LoanExtractionService();
        $this->assertTrue($service->extract($application));

        $fresh = $application->fresh();
        $this->assertSame(24, $fresh->extracted_data['requested_tenure']['value'] ?? null);
        $this->assertSame(24, $fresh->tenure_months);
    }

    public function test_employer_name_is_filled_from_business_name_answer(): void
    {
        $transcript = implode("\n", [
            'AI: আপনি কি চাকরি করেন, নাকি ব্যবসা? নাকি অন্য কিছু?',
            'Applicant: আমি ব্যবসা করি',
            'AI: আপনার ব্যবসার নাম কি?',
            'Applicant: ই জেনারেশন পিএলসি',
            'AI: আপনার মাসিক নিট আয় কত?',
            'Applicant: আমার মাসিক আয় হচ্ছে এক লক্ষ টাকা',
        ]);

        $application = $this->createSubmittedApplication($transcript);

        $this->fakeGeminiExtraction([
            'loan_type' => $this->field('personal'),
            'loan_purpose' => $this->field('জমি কেনার জন্য'),
            'requested_amount' => $this->field(100000),
            'requested_tenure' => $this->field(24),
            'exact_monthly_income' => $this->field(100000),
            'income_source' => $this->field('ব্যবসা'),
            'employer_name' => $this->field(null, '', 30),
            'other_regular_monthly_income' => $this->field(100000),
            'existing_monthly_obligations' => $this->field(0),
            'asset_value' => $this->field(null),
            'down_payment' => $this->field(null),
            'missing_fields' => ['employer_name'],
        ]);

        $service = new LoanExtractionService();
        $this->assertTrue($service->extract($application));

        $fresh = $application->fresh();
        $this->assertSame('ই জেনারেশন পিএলসি', $fresh->extracted_data['employer_name']['value'] ?? null);
        $this->assertSame('ই জেনারেশন পিএলসি', $fresh->employer_name);
    }

    private function createSubmittedApplication(string $transcript): LoanApplication
    {
        return LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'processing',
            'submitted_at' => now(),
            'transcript' => $transcript,
            'transcript_text' => $transcript,
        ]);
    }
}
