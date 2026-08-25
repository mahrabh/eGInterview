<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::latest()->paginate(10);
        return view('plans.index', compact('plans'));
    }

    public function create()
    {
        return view('plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans',
            'price' => 'required|numeric|min:0',
            'interview_limit' => 'required|integer|min:0',
            'ai_generation_limit' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['candidate_limit'] = 0;
        $validated['team_member_limit'] = 0;

        Plan::create($validated);

        return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return view('plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('plans')->ignore($plan->id)],
            'price' => 'required|numeric|min:0',
            'interview_limit' => 'required|integer|min:0',
            'ai_generation_limit' => 'required|integer|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['candidate_limit'] = $plan->candidate_limit ?? 0;
        $validated['team_member_limit'] = $plan->team_member_limit ?? 0;

        $plan->update($validated);

        return redirect()->route('plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return redirect()->route('plans.index')->with('success', 'Plan deleted successfully.');
    }
}
