<?php

namespace Tests\Feature;

use App\Models\Interview;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_all_modules_and_sees_role_label(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Workspace Admin']);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Admin', false);
        $this->actingAs($admin)->get('/recruitment')->assertOk();
        $this->actingAs($admin)->get('/loan-applications')->assertOk();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/plans')->assertOk();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertSee('System overview across recruitment and loan interviews.')
            ->assertSee('Candidates')
            ->assertSee('Loan Applicants');
    }

    public function test_recruiter_allowed_and_forbidden_routes(): void
    {
        $recruiter = User::factory()->create(['role' => 'recruiter', 'name' => 'Recruiter One']);

        $this->actingAs($recruiter)->get('/dashboard')->assertOk()
            ->assertSee('Your recruitment pipeline at a glance.')
            ->assertSee('Recruitment')
            ->assertDontSee('Loan Applicants')
            ->assertSee('Recruiter');

        $this->actingAs($recruiter)->get('/recruitment')->assertOk();
        $this->actingAs($recruiter)->get('/loan-applications')->assertForbidden();
        $this->actingAs($recruiter)->get('/users')->assertForbidden();
        $this->actingAs($recruiter)->get('/plans')->assertForbidden();
    }

    public function test_analyst_allowed_and_forbidden_routes(): void
    {
        $analyst = User::factory()->create(['role' => 'analyst', 'name' => 'Analyst One']);

        $this->actingAs($analyst)->get('/dashboard')->assertOk()
            ->assertSee('Your loan applications and review queue.')
            ->assertSee('Loan Applicants')
            ->assertDontSee('href="'.route('recruitment.index').'"', false)
            ->assertSee('Analyst')
            ->assertSee('Needs Review');

        $this->actingAs($analyst)->get('/loan-applications')->assertOk();
        $this->actingAs($analyst)->get('/recruitment')->assertForbidden();
        $this->actingAs($analyst)->get('/users')->assertForbidden();
        $this->actingAs($analyst)->get('/plans')->assertForbidden();
    }

    public function test_recruiter_only_sees_own_candidates(): void
    {
        $owner = User::factory()->create(['role' => 'recruiter']);
        $other = User::factory()->create(['role' => 'recruiter']);

        Interview::create([
            'candidate_name' => 'Mine Candidate',
            'candidate_email' => 'mine@example.com',
            'applied_role' => 'Dev',
            'job_description' => 'Build',
            'status' => 'draft',
            'user_id' => $owner->id,
        ]);

        Interview::create([
            'candidate_name' => 'Theirs Candidate',
            'candidate_email' => 'theirs@example.com',
            'applied_role' => 'QA',
            'job_description' => 'Test',
            'status' => 'draft',
            'user_id' => $other->id,
        ]);

        $this->actingAs($owner)
            ->get('/recruitment')
            ->assertOk()
            ->assertSee('Mine Candidate')
            ->assertDontSee('Theirs Candidate');
    }

    public function test_analyst_only_sees_own_loan_applicants(): void
    {
        $owner = User::factory()->create(['role' => 'analyst']);
        $other = User::factory()->create(['role' => 'analyst']);

        $mine = LoanApplicant::create([
            'name' => 'My Applicant',
            'phone' => '01710000011',
            'application_reference' => 'LA-MINE',
            'created_by' => $owner->id,
        ]);
        LoanApplication::create([
            'loan_applicant_id' => $mine->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $theirs = LoanApplicant::create([
            'name' => 'Their Applicant',
            'phone' => '01710000012',
            'application_reference' => 'LA-THEIR',
            'created_by' => $other->id,
        ]);
        LoanApplication::create([
            'loan_applicant_id' => $theirs->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $this->actingAs($owner)
            ->get('/loan-applications')
            ->assertOk()
            ->assertSee('My Applicant')
            ->assertDontSee('Their Applicant');
    }

    public function test_public_interview_routes_remain_accessible(): void
    {
        $owner = User::factory()->create(['role' => 'recruiter']);

        $interview = Interview::create([
            'candidate_name' => 'Public Candidate',
            'candidate_email' => 'public@example.com',
            'applied_role' => 'Dev',
            'job_description' => 'Build',
            'status' => 'approved',
            'public_url' => 'public-token-abc',
            'link_expires_at' => now()->addHours(48),
            'user_id' => $owner->id,
            'approved_questions' => ['Q1'],
        ]);

        $this->get('/join/'.$interview->public_url)->assertOk();
    }
}
