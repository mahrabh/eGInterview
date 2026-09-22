<?php

namespace Tests\Feature;

use App\Enums\BankOpeningStage;
use App\Models\BankOpeningApplicant;
use App\Models\BankOpeningApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankOpeningAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_analyst_and_both_can_access_bank_openings_recruiter_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $analyst = User::factory()->create(['role' => 'analyst']);
        $both = User::factory()->create(['role' => 'both']);
        $recruiter = User::factory()->create(['role' => 'recruiter']);

        $this->actingAs($admin)->get(route('bank-openings.index'))->assertOk();
        $this->actingAs($analyst)->get(route('bank-openings.index'))->assertOk();
        $this->actingAs($both)->get(route('bank-openings.index'))->assertOk();
        $this->actingAs($recruiter)->get(route('bank-openings.index'))->assertForbidden();
    }

    public function test_nav_shows_bank_opening_for_analyst_not_recruiter(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst']);
        $recruiter = User::factory()->create(['role' => 'recruiter']);

        $this->actingAs($analyst)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Bank Account Opening')
            ->assertSee(route('bank-openings.index'), false);

        $this->actingAs($recruiter)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Bank Account Opening');
    }

    public function test_analyst_only_sees_own_bank_opening_applications(): void
    {
        $owner = User::factory()->create(['role' => 'analyst']);
        $other = User::factory()->create(['role' => 'analyst']);

        $mine = $this->makeApplication($owner, 'BA-MINE001', 'My Bank Applicant');
        $theirs = $this->makeApplication($other, 'BA-THEIR01', 'Their Bank Applicant');

        $this->actingAs($owner)
            ->get(route('bank-openings.index'))
            ->assertOk()
            ->assertSee('My Bank Applicant')
            ->assertSee('BA-MINE001')
            ->assertDontSee('Their Bank Applicant')
            ->assertDontSee('BA-THEIR01');

        $this->actingAs($owner)
            ->get(route('bank-openings.show', $theirs))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('bank-openings.show', $mine))
            ->assertOk()
            ->assertSee('BA-MINE001');
    }

    public function test_analyst_cannot_delete_or_regenerate_others_application(): void
    {
        $owner = User::factory()->create(['role' => 'analyst']);
        $other = User::factory()->create(['role' => 'analyst']);
        $application = $this->makeApplication($other, 'BA-OTHER01', 'Other Applicant');

        $this->actingAs($owner)
            ->post(route('bank-openings.generate-link', $application))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('bank-openings.destroy', $application))
            ->assertForbidden();

        $this->assertDatabaseHas('bank_opening_applicants', [
            'application_reference' => 'BA-OTHER01',
        ]);
    }

    public function test_admin_can_view_all_bank_opening_applications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $analyst = User::factory()->create(['role' => 'analyst']);

        $this->makeApplication($analyst, 'BA-ADMIN01', 'Visible To Admin');

        $this->actingAs($admin)
            ->get(route('bank-openings.index'))
            ->assertOk()
            ->assertSee('Visible To Admin')
            ->assertSee('BA-ADMIN01');
    }

    private function makeApplication(User $creator, string $reference, string $name): BankOpeningApplication
    {
        $applicant = BankOpeningApplicant::create([
            'application_reference' => $reference,
            'name' => $name,
            'phone' => '01710000099',
            'created_by' => $creator->id,
        ]);

        return BankOpeningApplication::create([
            'bank_opening_applicant_id' => $applicant->id,
            'stage' => BankOpeningStage::Invited,
            'documents_required' => 3,
            'documents_uploaded' => 0,
        ]);
    }
}
