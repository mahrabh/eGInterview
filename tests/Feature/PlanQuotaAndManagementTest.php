<?php

namespace Tests\Feature;

use App\Enums\PlanModuleType;
use App\Exceptions\PlanQuotaExceededException;
use App\Models\Interview;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanQuotaAndManagementTest extends TestCase
{
    use RefreshDatabase;

    private PlanQuotaService $quota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quota = app(PlanQuotaService::class);
    }

    public function test_plan_compatibility_rules_for_roles(): void
    {
        $recruitment = Plan::factory()->recruitment()->create();
        $loan = Plan::factory()->loanApplicants()->create();
        $combined = Plan::factory()->combined()->create();
        $inactive = Plan::factory()->recruitment()->inactive()->create();

        $this->assertTrue($recruitment->isCompatibleWithRole('recruiter'));
        $this->assertFalse($recruitment->isCompatibleWithRole('analyst'));
        $this->assertTrue($loan->isCompatibleWithRole('analyst'));
        $this->assertFalse($loan->isCompatibleWithRole('recruiter'));
        $this->assertTrue($combined->isCompatibleWithRole('recruiter'));
        $this->assertTrue($combined->isCompatibleWithRole('analyst'));

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Bad Match',
            'email' => 'badmatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['analyst'],
            'plan_id' => $recruitment->id,
        ])->assertSessionHasErrors('plan_id');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Inactive Plan User',
            'email' => 'inactiveplan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['recruiter'],
            'plan_id' => $inactive->id,
        ])->assertSessionHasErrors('plan_id');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Good Match',
            'email' => 'goodmatch@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['analyst'],
            'plan_id' => $combined->id,
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'goodmatch@example.com',
            'plan_id' => $combined->id,
            'role' => 'analyst',
        ]);
    }

    public function test_monthly_usage_resets_each_calendar_month(): void
    {
        $plan = Plan::factory()->recruitment(5)->create();
        $user = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);

        $old = Interview::create([
            'candidate_name' => 'Last Month',
            'candidate_email' => 'old@example.com',
            'applied_role' => 'Dev',
            'job_description' => 'Build',
            'status' => 'draft',
            'user_id' => $user->id,
        ]);
        $old->created_at = now()->subMonth()->startOfMonth()->addDay();
        $old->save();

        Interview::create([
            'candidate_name' => 'This Month',
            'candidate_email' => 'new@example.com',
            'applied_role' => 'Dev',
            'job_description' => 'Build',
            'status' => 'draft',
            'user_id' => $user->id,
        ]);

        $this->assertSame(1, $this->quota->monthlyRecruitmentUsage($user));
        $this->assertSame(4, $this->quota->remainingRecruitment($user));
    }

    public function test_quota_enforced_on_recruitment_import_and_counts_once(): void
    {
        Storage::fake('local');
        $plan = Plan::factory()->recruitment(1)->create();
        $user = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);

        $csv = "candidate_name,applied_role,job_description,candidate_email\n"
            . "Alice,Dev,Build apps,alice@example.com\n"
            . "Bob,Dev,Build apps,bob@example.com\n";

        $file = UploadedFile::fake()->createWithContent('candidates.csv', $csv);

        $this->actingAs($user)
            ->post(route('interviews.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(1, Interview::where('user_id', $user->id)->count());
        $this->assertSame(0, $this->quota->remainingRecruitment($user->fresh()));
    }

    public function test_combined_plan_has_separate_monthly_limits(): void
    {
        $plan = Plan::factory()->combined(2, 3)->create();
        $recruiter = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);
        $analyst = User::factory()->create(['role' => 'analyst', 'plan_id' => $plan->id]);

        $this->assertTrue($plan->coversRecruitment());
        $this->assertTrue($plan->coversLoans());
        $this->assertSame(2, $plan->effectiveRecruitmentLimit());
        $this->assertSame(3, $plan->effectiveLoanLimit());

        $this->assertSame(2, $this->quota->recruitmentLimit($recruiter));
        $this->assertSame(3, $this->quota->loanLimit($recruiter));
        $this->assertSame(2, $this->quota->recruitmentLimit($analyst));
        $this->assertSame(3, $this->quota->loanLimit($analyst));
    }

    public function test_admin_bypasses_plan_and_quota(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'plan_id' => null]);

        $this->assertNull($this->quota->recruitmentLimit($admin));
        $this->assertNull($this->quota->loanLimit($admin));

        $csv = "candidate_name,applied_role,job_description,candidate_email\n"
            . "Admin Cand,Dev,Build,admincand@example.com\n";
        $file = UploadedFile::fake()->createWithContent('admin.csv', $csv);

        $this->actingAs($admin)
            ->post(route('interviews.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('interviews', [
            'candidate_email' => 'admincand@example.com',
            'user_id' => $admin->id,
        ]);
    }

    public function test_concurrent_slot_consumption_does_not_exceed_limit(): void
    {
        $plan = Plan::factory()->recruitment(1)->create();
        $user = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);

        $created = 0;
        $failed = 0;

        foreach ([1, 2] as $i) {
            try {
                $this->quota->withRecruitmentSlot($user, function () use ($user, $i, &$created) {
                    Interview::create([
                        'candidate_name' => "Cand {$i}",
                        'candidate_email' => "cand{$i}@example.com",
                        'applied_role' => 'Dev',
                        'job_description' => 'Build',
                        'status' => 'draft',
                        'user_id' => $user->id,
                    ]);
                    $created++;
                });
            } catch (PlanQuotaExceededException) {
                $failed++;
            }
        }

        $this->assertSame(1, $created);
        $this->assertSame(1, $failed);
        $this->assertSame(1, Interview::where('user_id', $user->id)->count());
    }

    public function test_existing_link_protected_when_quota_later_reached(): void
    {
        $plan = Plan::factory()->recruitment(1)->create();
        $user = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);

        $interview = Interview::create([
            'candidate_name' => 'Linked',
            'candidate_email' => 'linked@example.com',
            'applied_role' => 'Dev',
            'job_description' => 'Build',
            'status' => 'approved',
            'public_url' => Str::random(32),
            'approved_questions' => ['Q1'],
            'link_expires_at' => now()->addHours(24),
            'user_id' => $user->id,
        ]);

        // Exhaust remaining quota with another create attempt path: count already 1.
        $this->assertSame(0, $this->quota->remainingRecruitment($user));

        $oldUrl = $interview->public_url;

        $this->actingAs($user)
            ->post(route('interviews.regenerate-link', $interview))
            ->assertRedirect();

        $interview->refresh();
        $this->assertNotSame($oldUrl, $interview->public_url);
        $this->assertTrue($interview->link_expires_at->isFuture());

        $this->get('/join/' . $interview->public_url)->assertOk();
        $this->assertSame(1, Interview::where('user_id', $user->id)->count());
    }

    public function test_loan_import_quota_and_generate_link_does_not_consume(): void
    {
        Storage::fake('local');
        $plan = Plan::factory()->loanApplicants(1)->create();
        $analyst = User::factory()->create(['role' => 'analyst', 'plan_id' => $plan->id]);

        $csv = "applicant_name,phone_number\n"
            . "Loan One,01900000001\n"
            . "Loan Two,01900000002\n";
        $file = UploadedFile::fake()->createWithContent('loans.csv', $csv);

        $this->actingAs($analyst)
            ->post(route('loan-applications.import'), ['csv_file' => $file])
            ->assertRedirect();

        $this->assertSame(1, LoanApplicant::where('created_by', $analyst->id)->count());

        $application = LoanApplication::whereHas('applicant', fn ($q) => $q->where('created_by', $analyst->id))->first();
        $this->assertNotNull($application);

        $this->actingAs($analyst)
            ->post(route('loan-applications.generate-link', $application->id))
            ->assertRedirect();

        $this->assertSame(1, LoanApplicant::where('created_by', $analyst->id)->count());
        $application->refresh();
        $this->assertNotNull($application->public_token_hash);
    }

    public function test_cannot_delete_plan_assigned_to_users_deactivates_instead(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->recruitment()->create(['is_active' => true]);
        User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);

        $this->actingAs($admin)
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index'));

        $plan->refresh();
        $this->assertFalse($plan->is_active);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_plan_crud_persists_module_and_limits(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('plans.store'), [
            'name' => 'Combo Pro',
            'module_type' => PlanModuleType::Combined->value,
            'price' => 49.99,
            'recruitment_interview_limit' => 20,
            'loan_interview_limit' => 15,
            'ai_generation_limit' => 0,
            'is_active' => 1,
        ])->assertRedirect(route('plans.index'));

        $this->assertDatabaseHas('plans', [
            'slug' => 'combo-pro',
            'module_type' => 'combined',
            'recruitment_interview_limit' => 20,
            'loan_interview_limit' => 15,
            'interview_limit' => 20,
        ]);
    }

    public function test_plan_slug_is_auto_generated_and_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Plan::factory()->create(['name' => 'Basic', 'slug' => 'basic']);

        $this->actingAs($admin)->post(route('plans.store'), [
            'name' => 'Basic',
            'module_type' => PlanModuleType::Recruitment->value,
            'price' => 10,
            'recruitment_interview_limit' => 5,
            'loan_interview_limit' => 0,
            'is_active' => 1,
        ])->assertRedirect(route('plans.index'));

        $this->assertDatabaseHas('plans', [
            'name' => 'Basic',
            'slug' => 'basic-2',
        ]);
    }

    public function test_user_can_be_created_with_both_roles_and_combined_plan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->combined(10, 10)->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Dual User',
            'email' => 'dual@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['recruiter', 'analyst'],
            'plan_id' => $plan->id,
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'dual@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('both', $user->role);
        $this->assertTrue($user->canAccessRecruitment());
        $this->assertTrue($user->canAccessLoans());

        $this->actingAs($user)->get('/recruitment')->assertOk();
        $this->actingAs($user)->get('/loan-applications')->assertOk();
    }
}
