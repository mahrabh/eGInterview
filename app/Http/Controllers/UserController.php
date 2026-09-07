<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Services\PlanQuotaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private PlanQuotaService $planQuota)
    {
    }

    public function index()
    {
        $users = User::with('plan')
            ->withCount([
                'interviews as recruitment_month_count' => function ($query) {
                    $query->whereBetween('created_at', [
                        $this->planQuota->currentPeriodStart(),
                        $this->planQuota->currentPeriodEnd(),
                    ]);
                },
                'loanApplicants as loan_month_count' => function ($query) {
                    $query->whereBetween('created_at', [
                        $this->planQuota->currentPeriodStart(),
                        $this->planQuota->currentPeriodEnd(),
                    ]);
                },
            ])
            ->latest()
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('name')->get();

        return view('users.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'plan_id' => 'nullable|exists:plans,id',
            'expires_at' => 'nullable|date',
            'roles' => 'required|array|min:1',
            'roles.*' => ['string', Rule::in(['recruiter', 'analyst'])],
        ]);

        $role = User::roleFromSelections($validated['roles']);
        if ($role === null) {
            throw ValidationException::withMessages([
                'roles' => 'Select at least one workspace role.',
            ]);
        }

        $planId = $validated['plan_id'] ?? null;
        $this->planQuota->validateAssignablePlan($planId ? (int) $planId : null, $role);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $role,
            'plan_id' => $planId,
            'expires_at' => $this->normalizeExpiresAt($validated['expires_at'] ?? null),
        ]);

        return redirect()->route('users.index')->with('success', 'User account created successfully.');
    }

    public function edit(User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')
                ->with('error', 'The admin account cannot be edited from here. Use Account Settings instead.');
        }

        $plans = Plan::query()
            ->where(function ($query) use ($user) {
                $query->where('is_active', true);
                if ($user->plan_id) {
                    $query->orWhere('id', $user->plan_id);
                }
            })
            ->orderBy('name')
            ->get();

        return view('users.edit', compact('user', 'plans'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')
                ->with('error', 'The admin account cannot be edited from here.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'plan_id' => 'nullable|exists:plans,id',
            'expires_at' => 'nullable|date',
            'roles' => 'required|array|min:1',
            'roles.*' => ['string', Rule::in(['recruiter', 'analyst'])],
        ]);

        $role = User::roleFromSelections($validated['roles']);
        if ($role === null) {
            throw ValidationException::withMessages([
                'roles' => 'Select at least one workspace role.',
            ]);
        }

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8|confirmed']);
            $validated['password'] = $request->password;
        }

        $planId = $validated['plan_id'] ?? null;

        if ($planId !== null && (int) $planId !== (int) $user->plan_id) {
            $this->planQuota->validateAssignablePlan((int) $planId, $role);
        } elseif ($planId !== null && (int) $planId === (int) $user->plan_id) {
            $plan = Plan::query()->find((int) $planId);
            if ($plan && !$plan->isCompatibleWithRole($role)) {
                $this->planQuota->validateAssignablePlan((int) $planId, $role);
            }
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'plan_id' => $planId,
            'role' => $role,
            'expires_at' => $this->normalizeExpiresAt($validated['expires_at'] ?? null),
        ];

        if (isset($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->fill($payload);

        if (! $user->isDirty()) {
            return back()->with('info', 'No changes to save.');
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function assignPlan(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')->with('error', 'Cannot assign a plan to the admin account.');
        }

        $request->validate(['plan_id' => 'nullable|exists:plans,id']);

        $planId = $request->filled('plan_id') ? (int) $request->plan_id : null;
        $this->planQuota->validateAssignablePlan($planId, $user->role);

        $user->update(['plan_id' => $planId]);

        return redirect()->route('users.index')->with('success', "Plan assigned to {$user->name} successfully.");
    }

    public function destroy(User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')
                ->with('error', 'The admin account is protected and cannot be deleted.');
        }

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User account deleted.');
    }

    private function normalizeExpiresAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::parse($value)->startOfDay()->toDateTimeString();
    }
}
