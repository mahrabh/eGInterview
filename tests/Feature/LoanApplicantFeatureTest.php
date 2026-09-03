<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\User;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;

class LoanApplicantFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.gemini.key', 'test-gemini-key');
        Config::set('services.gemini.extraction_model', 'gemini-3.7-flash');
    }

    /** @return array{value: mixed, evidence: string, confidence: int} */
    private function field(mixed $value, int $confidence = 95): array
    {
        return ['value' => $value, 'evidence' => 'From transcript', 'confidence' => $confidence];
    }

    public function test_admin_can_view_all_applicants()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recruiter1 = User::factory()->create(['role' => 'recruiter']);
        $recruiter2 = User::factory()->create(['role' => 'recruiter']);

        $applicant1 = LoanApplicant::create(['name' => 'John', 'phone' => '0171', 'application_reference' => 'ref1', 'created_by' => $recruiter1->id]);
        $applicant2 = LoanApplicant::create(['name' => 'Jane', 'phone' => '0172', 'application_reference' => 'ref2', 'created_by' => $recruiter2->id]);
        
        LoanApplication::create(['loan_applicant_id' => $applicant1->id, 'loan_type' => 'personal', 'status' => 'draft']);
        LoanApplication::create(['loan_applicant_id' => $applicant2->id, 'loan_type' => 'personal', 'status' => 'draft']);

        $response = $this->actingAs($admin)->get('/loan-applications');
        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertSee('Jane');
    }

    public function test_recruiter_can_only_view_own_applicants()
    {
        $recruiter1 = User::factory()->create(['role' => 'recruiter']);
        $recruiter2 = User::factory()->create(['role' => 'recruiter']);

        $applicant1 = LoanApplicant::create(['name' => 'John', 'phone' => '0171', 'application_reference' => 'ref1', 'created_by' => $recruiter1->id]);
        $applicant2 = LoanApplicant::create(['name' => 'Jane', 'phone' => '0172', 'application_reference' => 'ref2', 'created_by' => $recruiter2->id]);
        
        LoanApplication::create(['loan_applicant_id' => $applicant1->id, 'loan_type' => 'personal', 'status' => 'draft']);
        LoanApplication::create(['loan_applicant_id' => $applicant2->id, 'loan_type' => 'personal', 'status' => 'draft']);

        $response = $this->actingAs($recruiter1)->get('/loan-applications');
        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertDontSee('Jane');
    }

    public function test_admin_can_filter_applicants_by_created_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recruiter1 = User::factory()->create(['role' => 'recruiter', 'name' => 'Recruiter One']);
        $recruiter2 = User::factory()->create(['role' => 'recruiter', 'name' => 'Recruiter Two']);

        $applicant1 = LoanApplicant::create(['name' => 'John', 'phone' => '0171', 'application_reference' => 'ref1', 'created_by' => $recruiter1->id]);
        $applicant2 = LoanApplicant::create(['name' => 'Jane', 'phone' => '0172', 'application_reference' => 'ref2', 'created_by' => $recruiter2->id]);

        LoanApplication::create(['loan_applicant_id' => $applicant1->id, 'loan_type' => 'personal', 'status' => 'draft']);
        LoanApplication::create(['loan_applicant_id' => $applicant2->id, 'loan_type' => 'personal', 'status' => 'draft']);

        $response = $this->actingAs($admin)->get('/loan-applications?user_id=' . $recruiter1->id);
        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertDontSee('Jane');
    }

    public function test_import_requires_file()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $response = $this->actingAs($user)->post('/loan-applications/import', []);
        $response->assertSessionHasErrors('csv_file');
    }

    public function test_import_processes_valid_csv_and_ignores_duplicates()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        
        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "Test User,01999999999\n";
        $csvContent .= "Test User,+8801999999999\n";
        $csvContent .= "Another User,01888888888\n";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($user)->post('/loan-applications/import', [
            'csv_file' => $file
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, 'Successfully imported: 2')
                && str_contains($message, 'duplicates in your list: 1');
        });
        
        $this->assertDatabaseCount('loan_applicants', 2);
        $this->assertDatabaseHas('loan_applicants', ['phone_hash' => hash('sha256', '01999999999')]);
        $this->assertDatabaseHas('loan_applicants', ['phone_hash' => hash('sha256', '01888888888')]);
        $this->assertDatabaseCount('loan_applications', 2);
    }

    public function test_reimporting_existing_phone_explains_duplicate_clearly()
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        LoanApplicant::create([
            'name' => 'John Doe',
            'phone' => '01712345678',
            'application_reference' => 'LA-EXIST',
            'created_by' => $user->id,
        ]);

        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "John Doe,01712345678\n";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($user)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('error', function ($message) {
            return str_contains($message, 'No new applicants imported')
                && str_contains($message, 'already exist in your list');
        });
        $this->assertDatabaseCount('loan_applicants', 1);
    }

    public function test_import_restores_leading_zero_when_excel_strips_it()
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "Excel User,1712345678\n";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($user)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('loan_applicants', [
            'phone_hash' => hash('sha256', '01712345678'),
            'created_by' => $user->id,
        ]);
    }

    public function test_import_accepts_common_bangladesh_phone_formats()
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "Local Zero,01911112222\n";
        $csvContent .= "Plus Country,+8801911113333\n";
        $csvContent .= "Country No Plus,8801911114444\n";
        $csvContent .= "Excel Stripped,1911115555\n";
        $csvContent .= "Spaced,'01911116666\n";

        $file = UploadedFile::fake()->createWithContent('phones.csv', $csvContent);

        $response = $this->actingAs($user)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, 'Successfully imported: 5');
        });

        foreach (['01911112222', '01911113333', '01911114444', '01911115555', '01911116666'] as $phone) {
            $this->assertDatabaseHas('loan_applicants', [
                'phone_hash' => hash('sha256', $phone),
                'created_by' => $user->id,
            ]);
        }
    }

    public function test_downloaded_template_can_be_imported(): void
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $tempPath = storage_path('app/testing_loan_template_import.xlsx');
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempPath);
        $writer->addRows([
            [
                'applicant_name' => 'Example Applicant',
                'phone_number' => '01900000001',
            ],
            [
                'applicant_name' => 'Another Example',
                'phone_number' => '01800000002',
            ],
        ]);
        $writer->close();

        $file = new UploadedFile(
            $tempPath,
            'loan_applicants_template.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, 'Successfully imported: 2');
        });

        $this->assertDatabaseCount('loan_applicants', 2);
        $this->assertDatabaseHas('loan_applicants', [
            'phone_hash' => hash('sha256', '01900000001'),
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('loan_applicants', [
            'phone_hash' => hash('sha256', '01800000002'),
            'created_by' => $user->id,
        ]);

        @unlink($tempPath);
    }

    public function test_import_allows_same_phone_for_different_recruiters()
    {
        $recruiterA = User::factory()->create(['role' => 'recruiter']);
        $recruiterB = User::factory()->create(['role' => 'recruiter']);

        LoanApplicant::create([
            'name' => 'Existing Applicant',
            'phone' => '01712345678',
            'application_reference' => 'LA-EXISTING',
            'created_by' => $recruiterA->id,
        ]);

        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "Same Phone User,01712345678\n";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($recruiterB)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseCount('loan_applicants', 2);
        $this->assertDatabaseHas('loan_applicants', [
            'phone_hash' => hash('sha256', '01712345678'),
            'created_by' => $recruiterB->id,
        ]);
    }

    public function test_phone_is_stored_masked_for_display()
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $applicant = LoanApplicant::create([
            'name' => 'Masked User',
            'phone' => '01951234592',
            'application_reference' => 'LA-MASK',
            'created_by' => $user->id,
        ]);

        $this->assertSame('195****592', $applicant->fresh()->masked_phone);
        $this->assertDatabaseHas('loan_applicants', [
            'application_reference' => 'LA-MASK',
            'phone_masked' => '195****592',
        ]);
    }

    public function test_import_blocks_duplicate_phone_for_same_recruiter()
    {
        $recruiter = User::factory()->create(['role' => 'recruiter']);

        LoanApplicant::create([
            'name' => 'Existing Applicant',
            'phone' => '01712345678',
            'application_reference' => 'LA-EXISTING',
            'created_by' => $recruiter->id,
        ]);

        $csvContent = "applicant_name,phone_number\n";
        $csvContent .= "Duplicate Phone User,01712345678\n";

        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($recruiter)->post('/loan-applications/import', [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('loan_applicants', 1);
    }
    
    public function test_can_delete_own_applicant()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create(['name' => 'John', 'phone' => '0171', 'application_reference' => 'ref1', 'created_by' => $user->id]);
        
        $application = LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->delete("/loan-applications/{$application->id}");
        $response->assertRedirect();
        
        $this->assertDatabaseCount('loan_applicants', 0);
        $this->assertDatabaseCount('loan_applications', 0);
    }

    public function test_cannot_delete_other_applicant()
    {
        $user1 = User::factory()->create(['role' => 'recruiter']);
        $user2 = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create(['name' => 'John', 'phone' => '0171', 'application_reference' => 'ref1', 'created_by' => $user1->id]);
        
        $application = LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user2)->delete("/loan-applications/{$application->id}");
        $response->assertStatus(403);
        
        $this->assertDatabaseCount('loan_applicants', 1);
        $this->assertDatabaseCount('loan_applications', 1);
    }

    public function test_loan_interview_flow()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create(['name' => 'John', 'phone' => '01712345678', 'application_reference' => 'LA-12345', 'created_by' => $user->id]);
        
        $application = LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'loan_type' => null,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->post("/loan-applications/{$application->id}/generate-link");
        $response->assertSessionHas('generated_link_url');
        $response->assertSessionHas('generated_link_id');
        
        $application->refresh();
        $this->assertNotNull($application->public_token_hash);

        $token = 'test-token-123';
        $application->public_token_hash = hash('sha256', $token);
        $application->public_token_expiry = now()->addDays(7);
        $application->save();

        $response = $this->get("/loan-interview/{$token}");
        $response->assertStatus(200);
        $response->assertSee('John');

        $response = $this->postJson("/loan-interview/{$token}/start", [
            'nid' => '12345678901'
        ]);
        $response->assertStatus(200);
        $applicant->refresh();
        $this->assertEquals('8901', $applicant->nid_last_four);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'loan_type' => $this->field('personal'),
                                'loan_purpose' => $this->field('Medical'),
                                'requested_amount' => $this->field(500000),
                                'requested_tenure' => $this->field(36),
                                'exact_monthly_income' => $this->field(80000),
                                'income_source' => $this->field('Salaried'),
                                'employer_name' => $this->field('ABC Ltd'),
                                'other_regular_monthly_income' => $this->field(0),
                                'existing_monthly_obligations' => $this->field(5000),
                                'asset_value' => $this->field(null),
                                'down_payment' => $this->field(null),
                                'missing_fields' => [],
                                'contradictions' => [],
                                'needs_confirmation' => false,
                            ]),
                        ]],
                    ],
                    'finishReason' => 'STOP',
                ]],
            ], 200),
        ]);

        $response = $this->postJson("/loan-interview/{$token}/transcript", [
            'transcript_text' => 'AI: Hello. Applicant: Personal loan five lakh for medical bills.'
        ]);
        
        $response->assertStatus(200);
        $application->refresh();
        $this->assertEquals('assessed', $application->status);
        $this->assertEquals('Indicatively Eligible', $application->outcome);
    }

    public function test_incomplete_session_saves_as_needs_review()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create(['name' => 'Jane', 'phone' => '01812345678', 'application_reference' => 'LA-12346', 'created_by' => $user->id]);
        
        $application = LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'status' => 'draft',
            'public_token_hash' => hash('sha256', 'incomplete-token'),
            'public_token_expiry' => now()->addDays(7)
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'loan_type' => $this->field('personal'),
                            'loan_purpose' => $this->field(null, 40),
                            'requested_amount' => $this->field(null, 40),
                            'requested_tenure' => $this->field(null, 40),
                            'exact_monthly_income' => $this->field(50000),
                            'income_source' => $this->field(null, 40),
                            'employer_name' => $this->field(null, 40),
                            'other_regular_monthly_income' => $this->field(0),
                            'existing_monthly_obligations' => $this->field(0),
                            'asset_value' => $this->field(null),
                            'down_payment' => $this->field(null),
                            'missing_fields' => ['requested_amount', 'requested_tenure'],
                            'contradictions' => [],
                            'needs_confirmation' => true,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
            ], 200),
        ]);

        $response = $this->postJson("/loan-interview/incomplete-token/transcript", [
            'transcript_text' => 'Applicant: I want to end session now.'
        ]);
        
        $response->assertStatus(200);
        $application->refresh();
        
        $this->assertContains($application->status, ['assessed', 'needs_review']);
        $this->assertEquals('personal', $application->loan_type);
        $this->assertEquals('50000.00', (string) $application->monthly_income);
        $this->assertNotNull($application->outcome);
    }

    public function test_unclear_amount_saves_as_needs_confirmation()
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $applicant = LoanApplicant::create(['name' => 'Alice', 'phone' => '01912345678', 'application_reference' => 'LA-12347', 'created_by' => $user->id]);
        
        $application = LoanApplication::create([
            'loan_applicant_id' => $applicant->id,
            'status' => 'draft',
            'public_token_hash' => hash('sha256', 'unclear-token'),
            'public_token_expiry' => now()->addDays(7)
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'loan_type' => $this->field('car'),
                            'loan_purpose' => $this->field('Vehicle'),
                            'requested_amount' => $this->field('20-30 thousand', 40),
                            'requested_tenure' => $this->field(60),
                            'exact_monthly_income' => $this->field(100000),
                            'income_source' => $this->field('Salaried'),
                            'employer_name' => $this->field('XYZ'),
                            'other_regular_monthly_income' => $this->field(0),
                            'existing_monthly_obligations' => $this->field(0),
                            'asset_value' => $this->field(null, 40),
                            'down_payment' => $this->field(null, 40),
                            'missing_fields' => ['requested_amount', 'asset_value', 'down_payment'],
                            'contradictions' => [],
                            'needs_confirmation' => true,
                        ]),
                    ]]],
                    'finishReason' => 'STOP',
                ]],
            ], 200),
        ]);

        $response = $this->postJson("/loan-interview/unclear-token/transcript", [
            'transcript_text' => 'Candidate: amount is unclear'
        ]);
        $response->assertStatus(200);
        
        $application->refresh();
        $this->assertContains($application->status, ['assessed', 'needs_review']);
        $this->assertNull($application->extracted_data['requested_amount']['value'] ?? null);
        $this->assertNotNull($application->outcome);
    }
}
