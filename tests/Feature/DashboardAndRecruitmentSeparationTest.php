<?php

namespace Tests\Feature;

use App\Models\Interview;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndRecruitmentSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_overview_not_candidate_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('System overview across recruitment and loan interviews.');
        $response->assertSee('Candidates');
        $response->assertSee('Loan Applicants');
        $response->assertDontSee('Import CSV');
        $response->assertDontSee('Download Template');
    }

    public function test_recruitment_page_hosts_candidate_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Interview::create([
            'candidate_name' => 'Alice Candidate',
            'candidate_email' => 'alice@example.com',
            'applied_role' => 'Engineer',
            'job_description' => 'Build software',
            'status' => 'draft',
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/recruitment');

        $response->assertOk();
        $response->assertSee('Recruitment');
        $response->assertSee('Alice Candidate');
        $response->assertSee('Import CSV');
        $response->assertSee('Download Template');
    }

    public function test_recruiter_dashboard_does_not_expose_other_users_records(): void
    {
        $recruiterA = User::factory()->create(['role' => 'recruiter']);
        $recruiterB = User::factory()->create(['role' => 'recruiter']);

        Interview::create([
            'candidate_name' => 'Owned Candidate',
            'candidate_email' => 'owned@example.com',
            'applied_role' => 'Analyst',
            'job_description' => 'Analyze data',
            'status' => 'completed',
            'user_id' => $recruiterA->id,
        ]);

        Interview::create([
            'candidate_name' => 'Other Candidate',
            'candidate_email' => 'other@example.com',
            'applied_role' => 'Manager',
            'job_description' => 'Manage team',
            'status' => 'completed',
            'user_id' => $recruiterB->id,
        ]);

        $response = $this->actingAs($recruiterA)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Owned Candidate');
        $response->assertDontSee('Other Candidate');
        $response->assertSee('Completed');
        $response->assertDontSee('Loan Applicants');
    }

    public function test_analyst_dashboard_does_not_expose_other_users_loans(): void
    {
        $analystA = User::factory()->create(['role' => 'analyst']);
        $analystB = User::factory()->create(['role' => 'analyst']);

        $applicantA = LoanApplicant::create([
            'name' => 'Loan Owner',
            'phone' => '01710000001',
            'application_reference' => 'LA-OWN',
            'created_by' => $analystA->id,
        ]);
        LoanApplication::create([
            'loan_applicant_id' => $applicantA->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $applicantB = LoanApplicant::create([
            'name' => 'Loan Other',
            'phone' => '01710000002',
            'application_reference' => 'LA-OTH',
            'created_by' => $analystB->id,
        ]);
        LoanApplication::create([
            'loan_applicant_id' => $applicantB->id,
            'loan_type' => 'personal',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($analystA)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Loan Owner');
        $response->assertDontSee('Loan Other');
        $response->assertSee('Needs Review');
        $response->assertDontSee('>Candidates</');
    }

    public function test_approve_redirects_to_recruitment_index(): void
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $interview = Interview::create([
            'candidate_name' => 'Approve Me',
            'candidate_email' => 'approve@example.com',
            'applied_role' => 'Designer',
            'job_description' => 'Design products',
            'status' => 'pending',
            'user_id' => $user->id,
            'approved_questions' => ['Q1', 'Q2'],
        ]);

        $response = $this->actingAs($user)
            ->post("/interviews/{$interview->id}/approve", [
                'questions' => ['Q1', 'Q2'],
            ]);

        $response->assertRedirect(route('recruitment.index'));
    }

    public function test_navigation_includes_recruitment_link(): void
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('recruitment.index').'"', false)
            ->assertSee('Recruitment');
    }
}
