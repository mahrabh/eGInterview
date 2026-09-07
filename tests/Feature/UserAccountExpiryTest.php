<?php

namespace Tests\Feature;

use App\Models\Interview;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAccountExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_expiry_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->recruitment()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Expires Soon',
            'email' => 'expires@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['recruiter'],
            'plan_id' => $plan->id,
            'expires_at' => '2030-12-31',
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'expires@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('2030-12-31', $user->expires_at->toDateString());
        $this->assertFalse($user->isExpired());
    }

    public function test_expired_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => 'recruiter',
            'expires_at' => now()->subDay()->startOfDay(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_login_on_expiry_day(): void
    {
        $user = User::factory()->create([
            'role' => 'recruiter',
            'expires_at' => now()->startOfDay(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_session_is_blocked_from_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'analyst',
            'expires_at' => now()->addDay()->startOfDay(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['expires_at' => now()->subDay()->startOfDay()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_never_expires(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'expires_at' => now()->subYear()->startOfDay(),
        ]);

        $this->assertFalse($admin->isExpired());

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_clear_user_expiry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->loanApplicants()->create();
        $user = User::factory()->create([
            'role' => 'analyst',
            'plan_id' => $plan->id,
            'expires_at' => now()->addMonth()->startOfDay(),
        ]);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['analyst'],
            'plan_id' => $plan->id,
            'expires_at' => '',
        ])->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh()->expires_at);
    }

    public function test_public_interview_routes_remain_available_when_creator_is_expired(): void
    {
        $recruiter = User::factory()->create([
            'role' => 'recruiter',
            'expires_at' => now()->subDay()->startOfDay(),
        ]);

        $token = (string) \Illuminate\Support\Str::uuid();

        Interview::create([
            'candidate_name' => 'Public Candidate',
            'candidate_email' => 'candidate@example.com',
            'applied_role' => 'Engineer',
            'job_description' => 'Build things',
            'status' => 'approved',
            'user_id' => $recruiter->id,
            'public_url' => $token,
            'approved_questions' => ['Tell me about yourself'],
            'link_expires_at' => now()->addDays(7),
        ]);

        $this->get(route('interview.public', $token))->assertOk();
    }
}
