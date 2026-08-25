<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('plan')->withCount('interviews')->latest()->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        return view('users.create', compact('plans'));
    }

    /**
     * Store a new recruiter — always role=recruiter.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'plan_id'  => 'nullable|exists:plans,id',
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
            'role'     => 'recruiter',
            'plan_id'  => $validated['plan_id'] ?? null,
        ]);

        return redirect()->route('users.index')->with('success', 'Recruiter account created successfully.');
    }

    /**
     * Edit a recruiter — admin account cannot be edited here.
     */
    public function edit(User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')
                ->with('error', 'The admin account cannot be edited from here. Use Account Settings instead.');
        }

        $plans = Plan::where('is_active', true)->orderBy('name')->get();
        return view('users.edit', compact('user', 'plans'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')
                ->with('error', 'The admin account cannot be edited from here.');
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8|confirmed']);
            $validated['password'] = $request->password;
        }

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'Recruiter updated successfully.');
    }

    /**
     * Assign/change the plan of a recruiter.
     */
    public function assignPlan(Request $request, User $user)
    {
        if ($user->isAdmin()) {
            return redirect()->route('users.index')->with('error', 'Cannot assign a plan to the admin account.');
        }

        $request->validate(['plan_id' => 'nullable|exists:plans,id']);

        $user->update(['plan_id' => $request->plan_id]);

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

        return redirect()->route('users.index')->with('success', 'Recruiter account deleted.');
    }
}
