<?php

namespace Tests\Feature;

use App\Models\BillingHistory;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_assignment_creates_pending_invoice_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->recruitment()->create(['price' => 49.00, 'name' => 'Basic']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Billed User',
            'email' => 'billed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['recruiter'],
            'plan_id' => $plan->id,
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'billed@example.com')->firstOrFail();

        $this->assertDatabaseHas('billing_histories', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_name' => 'Basic',
            'amount' => 49.00,
            'status' => 'pending',
        ]);
    }

    public function test_user_sees_only_own_billing_and_can_download(): void
    {
        $plan = Plan::factory()->recruitment()->create(['price' => 20]);
        $owner = User::factory()->create(['role' => 'recruiter', 'plan_id' => $plan->id]);
        $other = User::factory()->create(['role' => 'analyst']);

        $own = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0001',
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_module' => 'Recruitment',
            'amount' => 20,
            'currency' => 'USD',
            'gateway' => 'Bank Transfer',
            'description' => 'Test invoice',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $foreign = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0002',
            'user_id' => $other->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_module' => 'Recruitment',
            'amount' => 30,
            'currency' => 'USD',
            'description' => 'Other invoice',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('INV-TEST-0001')
            ->assertDontSee('INV-TEST-0002');

        $this->actingAs($owner)->get(route('billing.invoice.download', $own))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($owner)->get(route('billing.invoice.download', $foreign))
            ->assertForbidden();
    }

    public function test_admin_can_update_payment_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'recruiter']);
        $billing = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0003',
            'user_id' => $user->id,
            'plan_name' => 'Basic',
            'amount' => 10,
            'currency' => 'USD',
            'description' => 'Pending payment',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->put(route('billing.update', $billing), [
            'amount' => 10,
            'gateway' => 'PayPal',
            'status' => 'paid',
        ])->assertRedirect(route('billing.index'));

        $this->assertSame('paid', $billing->fresh()->status);
        $this->assertNotNull($billing->fresh()->paid_at);
    }

    public function test_non_admin_cannot_manage_billing_records(): void
    {
        $user = User::factory()->create(['role' => 'recruiter']);
        $billing = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0004',
            'user_id' => $user->id,
            'amount' => 5,
            'currency' => 'USD',
            'description' => 'Own',
            'status' => 'pending',
        ]);

        $this->actingAs($user)->put(route('billing.update', $billing), [
            'amount' => 9,
            'status' => 'paid',
            'gateway' => 'PayPal',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('billing.void', $billing))->assertForbidden();
    }

    public function test_admin_can_void_and_restore_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'recruiter']);
        $billing = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0005',
            'user_id' => $user->id,
            'amount' => 40,
            'currency' => 'USD',
            'description' => 'To void',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('billing.void', $billing))
            ->assertRedirect(route('billing.index'));

        $this->assertSame('void', $billing->fresh()->status);
        $this->assertNull($billing->fresh()->paid_at);
        $this->assertNotNull($billing->fresh()->voided_at);
        $this->assertDatabaseHas('billing_histories', ['id' => $billing->id]);

        $this->actingAs($admin)->post(route('billing.restore', $billing))
            ->assertRedirect(route('billing.index'));

        $this->assertSame('pending', $billing->fresh()->status);
        $this->assertNull($billing->fresh()->voided_at);
    }

    public function test_voided_invoices_are_purged_after_retention_window(): void
    {
        $user = User::factory()->create(['role' => 'recruiter']);

        $expired = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0007',
            'user_id' => $user->id,
            'amount' => 12,
            'currency' => 'USD',
            'description' => 'Old void',
            'status' => 'void',
            'voided_at' => now()->subDays(31),
        ]);

        $recent = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0008',
            'user_id' => $user->id,
            'amount' => 15,
            'currency' => 'USD',
            'description' => 'Recent void',
            'status' => 'void',
            'voided_at' => now()->subDays(5),
        ]);

        $this->artisan('billing:purge-voided')->assertSuccessful();

        $this->assertDatabaseMissing('billing_histories', ['id' => $expired->id]);
        $this->assertDatabaseHas('billing_histories', ['id' => $recent->id, 'status' => 'void']);
    }

    public function test_admin_cannot_edit_voided_invoice_to_paid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'recruiter']);
        $billing = BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0009',
            'user_id' => $user->id,
            'amount' => 20,
            'currency' => 'USD',
            'description' => 'Voided',
            'status' => 'void',
            'voided_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('billing.update', $billing), [
            'amount' => 20,
            'gateway' => 'Bank Transfer',
            'status' => 'paid',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('void', $billing->fresh()->status);
    }

    public function test_recreate_is_blocked_when_voided_invoice_can_be_restored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->recruitment()->create(['price' => 55, 'name' => 'Pro']);
        $user = User::factory()->create([
            'role' => 'recruiter',
            'plan_id' => $plan->id,
        ]);

        BillingHistory::query()->create([
            'invoice_number' => 'INV-TEST-0006',
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'amount' => 55,
            'currency' => 'USD',
            'description' => 'Old voided',
            'status' => 'void',
            'voided_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('billing.recreate', $user))
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame(1, BillingHistory::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, BillingHistory::query()->where('user_id', $user->id)->where('status', 'pending')->count());
    }

    public function test_admin_can_recreate_invoice_when_plan_assigned_and_no_invoice_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = Plan::factory()->recruitment()->create(['price' => 55, 'name' => 'Pro']);
        $user = User::factory()->create([
            'role' => 'recruiter',
            'plan_id' => $plan->id,
        ]);

        $this->actingAs($admin)->post(route('billing.recreate', $user))
            ->assertRedirect(route('billing.index'));

        $this->assertDatabaseHas('billing_histories', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'amount' => 55,
        ]);
    }
}
