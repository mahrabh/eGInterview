<?php

namespace Tests\Feature;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplicant;
use App\Models\BankOpeningApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankOpeningApplicationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_create_blank_invite_with_hashed_48h_token(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);

        $response = $this->actingAs($analyst)
            ->post(route('bank-openings.store'));

        $response->assertRedirect(route('bank-openings.index'));
        $response->assertSessionHas('generated_link_url');
        $response->assertSessionHas('generated_link_id');

        $this->assertDatabaseCount('bank_opening_applicants', 1);
        $this->assertDatabaseCount('bank_opening_applications', 1);

        $application = BankOpeningApplication::with('applicant')->first();
        $this->assertNotNull($application);
        $this->assertSame(BankOpeningStage::Invited, $application->stage);
        $this->assertNull($application->applicant->name);
        $this->assertNull($application->applicant->phone);
        $this->assertNotNull($application->public_token_hash);
        $this->assertTrue($application->public_token_expiry->greaterThan(now()->addHours(47)));
        $this->assertTrue($application->public_token_expiry->lessThanOrEqualTo(now()->addHours(48)->addMinute()));

        $plainToken = $application->reconstructPublicToken();
        $this->assertNotNull($plainToken);
        $this->assertSame(hash('sha256', $plainToken), $application->public_token_hash);
        $this->assertDatabaseMissing('bank_opening_applications', [
            'id' => $application->id,
            'public_token_hash' => $plainToken,
        ]);
    }

    public function test_regenerating_link_keeps_application_data_and_rotates_token(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);

        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-KEEP001',
            'name' => 'Kept Applicant',
            'phone' => '01715550001',
            'created_by' => $analyst->id,
        ]);

        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'account_type' => 'savers',
            'stage' => BankOpeningStage::InformationSubmitted,
            'documents_required' => 3,
            'documents_uploaded' => 2,
            'information_submitted_at' => now()->subDay(),
        ]);

        $oldToken = $application->issuePublicToken();
        $oldHash = $application->public_token_hash;
        $oldExpiry = $application->public_token_expiry->copy();

        // Ensure expiry timestamp can change.
        $this->travel(2)->seconds();

        $this->actingAs($analyst)
            ->post(route('bank-openings.generate-link', $application))
            ->assertRedirect();

        $application->refresh()->load('applicant');

        $this->assertNotSame($oldHash, $application->public_token_hash);
        $this->assertTrue($application->public_token_expiry->greaterThan($oldExpiry));
        $this->assertTrue($application->public_token_expiry->greaterThan(now()->addHours(47)));
        $this->assertTrue($application->public_token_expiry->lessThanOrEqualTo(now()->addHours(48)->addMinute()));
        $this->assertSame('Kept Applicant', $application->applicant->name);
        $this->assertSame('savers', $application->account_type);
        $this->assertSame(BankOpeningStage::InformationSubmitted, $application->stage);
        $this->assertSame(2, $application->documents_uploaded);
        $this->assertSame(hash('sha256', $oldToken), $oldHash);

        $this->get(route('bank-opening.public', $oldToken))->assertNotFound();
        $this->get(route('bank-opening.public', $application->reconstructPublicToken()))->assertOk();
    }

    public function test_regenerating_link_after_interview_uses_24h_validity(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);

        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-DOCS24',
            'name' => 'Docs Window Applicant',
            'phone' => '01715550024',
            'created_by' => $analyst->id,
        ]);

        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'account_type' => 'savings',
            'account_type_confirmed' => true,
            'stage' => BankOpeningStage::DocumentsPending,
            'interview_completed_at' => now()->subHour(),
        ]);
        $application->issuePublicToken(BankOpeningApplication::PUBLIC_LINK_HOURS_BEFORE_INTERVIEW);

        $this->travel(2)->seconds();

        $this->actingAs($analyst)
            ->post(route('bank-openings.generate-link', $application))
            ->assertRedirect()
            ->assertSessionHas('generated_link_url');

        $application->refresh();
        $this->assertTrue($application->public_token_expiry->greaterThan(now()->addHours(23)));
        $this->assertTrue($application->public_token_expiry->lessThanOrEqualTo(now()->addHours(24)->addMinute()));
    }

    public function test_public_token_accepts_valid_link_and_rejects_expired_or_invalid(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-PUB001',
            'created_by' => $analyst->id,
        ]);
        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'stage' => BankOpeningStage::Invited,
        ]);

        $token = $application->issuePublicToken();

        $this->get(route('bank-opening.public', $token))
            ->assertOk()
            ->assertSee('BA-PUB001');

        $this->get(route('bank-opening.public', 'totally-invalid-token'))
            ->assertNotFound();

        $application->public_token_expiry = now()->subMinute();
        $application->save();

        $this->get(route('bank-opening.public', $token))
            ->assertNotFound();
    }

    public function test_public_information_submission_updates_stage_without_auth(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-INFO001',
            'created_by' => $analyst->id,
        ]);
        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'stage' => BankOpeningStage::Invited,
        ]);
        $token = $application->issuePublicToken();

        $this->post(route('bank-opening.information', $token), [
            'name' => 'Public Applicant',
            'phone' => '01718880001',
        ])->assertRedirect(route('bank-opening.public', $token));

        $application->refresh()->load('applicant');

        $this->assertSame('Public Applicant', $application->applicant->name);
        $this->assertSame('01718880001', $application->applicant->phone);
        $this->assertNull($application->account_type);
        $this->assertSame(BankOpeningStage::InformationSubmitted, $application->stage);
        $this->assertNotNull($application->information_submitted_at);
        $this->assertDatabaseHas('bank_opening_application_events', [
            'bank_opening_application_id' => $application->id,
            'event_type' => 'information_submitted',
        ]);
    }

    public function test_index_supports_search_stage_filter_and_pagination_columns(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);

        $a = BankOpeningApplicant::create([
            'application_reference' => 'BA-SEARCH1',
            'name' => 'Alpha Person',
            'phone' => '01710001111',
            'created_by' => $analyst->id,
        ]);
        BankOpeningApplication::create([
            'bank_opening_applicant_id' => $a->id,
            'account_type' => 'savings',
            'account_type_confirmed' => true,
            'stage' => BankOpeningStage::DocumentsPending,
            'documents_required' => 3,
            'documents_uploaded' => 1,
        ]);

        $b = BankOpeningApplicant::create([
            'application_reference' => 'BA-OTHER2',
            'name' => 'Beta Person',
            'phone' => '01710002222',
            'created_by' => $analyst->id,
        ]);
        BankOpeningApplication::create([
            'bank_opening_applicant_id' => $b->id,
            'stage' => BankOpeningStage::Invited,
        ]);

        $this->actingAs($analyst)
            ->get(route('bank-openings.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Person')
            ->assertSee('BA-SEARCH1')
            ->assertDontSee('Beta Person');

        $this->actingAs($analyst)
            ->get(route('bank-openings.index', ['stage' => BankOpeningStage::DocumentsPending->value]))
            ->assertOk()
            ->assertSee('Alpha Person')
            ->assertDontSee('Beta Person')
            ->assertDontSee('>Documents</th>', false);
    }

    public function test_status_snapshot_returns_progress_for_owned_applications(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $other = User::factory()->create(['role' => 'analyst']);

        $ownApplicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-SNAP1',
            'name' => 'Snap Own',
            'created_by' => $analyst->id,
        ]);
        $own = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $ownApplicant->id,
            'stage' => BankOpeningStage::DocumentsPending,
            'documents_required' => 3,
            'account_type' => 'savings',
            'account_type_confirmed' => true,
        ]);

        $otherApplicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-SNAP2',
            'name' => 'Snap Other',
            'created_by' => $other->id,
        ]);
        $foreign = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $otherApplicant->id,
            'stage' => BankOpeningStage::Invited,
            'documents_required' => 3,
        ]);

        $this->actingAs($analyst)
            ->getJson(route('bank-openings.status-snapshot', [
                'ids' => $own->id.','.$foreign->id,
            ]))
            ->assertOk()
            ->assertJsonPath('applications.'.$own->id.'.stage', BankOpeningStage::DocumentsPending->value)
            ->assertJsonPath('applications.'.$own->id.'.account_type', 'savings')
            ->assertJsonPath('applications.'.$own->id.'.documents_label', '0/5')
            ->assertJsonMissingPath('applications.'.$foreign->id);

        $this->actingAs($analyst)
            ->get(route('bank-openings.index'))
            ->assertOk()
            ->assertSee('status-snapshot', false)
            ->assertSee('pollEveryMs', false);
    }

    public function test_request_resubmission_issues_fresh_public_link_for_applicant(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-RESUB01',
            'name' => 'Resub Applicant',
            'phone' => '01719990001',
            'created_by' => $analyst->id,
        ]);
        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'account_type' => 'savings',
            'account_type_confirmed' => true,
            'account_type_name_snapshot' => 'Savings Account',
            'stage' => BankOpeningStage::Submitted,
            'submitted_at' => now()->subHour(),
            'documents_submitted_at' => now()->subHour(),
            'documents_required' => 1,
            'documents_uploaded' => 1,
        ]);
        $oldToken = $application->issuePublicToken();

        // Simulate the old buggy path: bump expiry without rotating the HMAC token.
        $application->public_token_expiry = now()->addHours(12);
        $application->save();
        $this->assertNull($application->fresh()->publicUrl());

        $this->travel(2)->seconds();

        $response = $this->actingAs($analyst)
            ->post(route('bank-openings.request-resubmission', $application), [
                'reason' => 'Please re-upload a clearer NID photo.',
                'groups' => ['applicant_photo_id'],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('generated_link_url');
        $response->assertSessionHas('generated_link_id', $application->id);

        $application->refresh();
        $this->assertSame(BankOpeningStage::ResubmissionRequired, $application->stage);
        $this->assertNull($application->submitted_at);
        $this->assertTrue($application->public_token_expiry?->greaterThan(now()->addHours(23)));
        $this->assertTrue($application->public_token_expiry?->lessThanOrEqualTo(now()->addHours(24)->addMinute()));

        $newUrl = session('generated_link_url');
        $this->assertNotEmpty($newUrl);
        $this->assertSame($newUrl, $application->publicUrl());
        $this->assertNotSame($oldToken, $application->reconstructPublicToken());

        $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application))
            ->assertOk()
            ->assertSee('Applicant resubmission link')
            ->assertSee($application->publicUrl());

        $this->get(route('bank-opening.public', $application->reconstructPublicToken()))
            ->assertOk();
    }

    public function test_applicant_resubmission_keeps_stage_until_submit_then_marks_fulfilled(): void
    {
        Storage::fake('local');

        $analyst = User::factory()->create(['role' => 'analyst']);
        $applicant = BankOpeningApplicant::create([
            'application_reference' => 'BA-RESUB02',
            'name' => 'Resub Flow',
            'phone' => '01719990002',
            'created_by' => $analyst->id,
        ]);
        $application = BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'account_type' => 'savings',
            'account_type_confirmed' => true,
            'account_type_name_snapshot' => 'Savings Account',
            'stage' => BankOpeningStage::Submitted,
            'submitted_at' => now()->subHour(),
            'documents_submitted_at' => now()->subHour(),
            'interview_completed_at' => now()->subHours(2),
            'documents_required' => 1,
            'documents_uploaded' => 1,
        ]);
        $token = $application->issuePublicToken();

        $this->actingAs($analyst)
            ->post(route('bank-openings.request-resubmission', $application), [
                'reason' => 'NID is blurry.',
                'groups' => ['applicant_photo_id'],
            ])
            ->assertRedirect()
            ->assertSessionHas('generated_link_url');

        $application->refresh();
        $token = $application->reconstructPublicToken();
        $this->assertSame(BankOpeningStage::ResubmissionRequired, $application->stage);
        $this->assertNotNull(data_get($application->meta, 'resubmission.reason'));

        $this->post(route('bank-opening.documents.store', $token), [
            'document_type' => 'applicant_photo_id',
            'file' => UploadedFile::fake()->image('nid-resub.jpg'),
        ])->assertOk();

        $application->refresh();
        $this->assertSame(BankOpeningStage::ResubmissionRequired, $application->stage);

        $this->postJson(route('bank-opening.submit', $token))
            ->assertOk()
            ->assertJsonPath('resubmission_fulfilled', true)
            ->assertJsonPath('application_submitted', true);

        $application->refresh();
        $this->assertSame(BankOpeningStage::Submitted, $application->stage);
        $this->assertNull(data_get($application->meta, 'resubmission'));
        $this->assertSame('fulfilled', data_get($application->meta, 'last_resubmission.status'));
        $this->assertSame(['applicant_photo_id'], data_get($application->meta, 'last_resubmission.groups'));

        $this->actingAs($analyst)
            ->get(route('bank-openings.show', $application))
            ->assertOk()
            ->assertSee('Resubmission completed')
            ->assertSee('Applicant submitted the requested documents');
    }
}
