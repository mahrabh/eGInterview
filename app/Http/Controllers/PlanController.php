<?php

namespace App\Http\Controllers;

use App\Enums\PlanModuleType;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
            ->withCount('users')
            ->latest()
            ->paginate(10);

        return view('plans.index', compact('plans'));
    }

    public function create()
    {
        return view('plans.create', [
            'moduleTypes' => PlanModuleType::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPlanPayload($request);
        $validated['slug'] = $this->uniqueSlugFromName($validated['name']);

        Plan::create($validated);

        return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        $plan->loadCount('users');

        return view('plans.edit', [
            'plan' => $plan,
            'moduleTypes' => PlanModuleType::cases(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $this->validatedPlanPayload($request, $plan);
        // Keep existing slug stable on edit (system-managed identifier).
        $validated['slug'] = $plan->slug;

        $plan->fill($validated);

        if (! $plan->isDirty()) {
            return back()->with('info', 'No changes to save.');
        }

        $plan->save();

        return redirect()->route('plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->users()->exists()) {
            $plan->update(['is_active' => false]);

            return redirect()->route('plans.index')->with(
                'error',
                'This plan is assigned to users and cannot be deleted. It has been deactivated instead.'
            );
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan deleted successfully.');
    }

    private function validatedPlanPayload(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'module_type' => ['required', Rule::in(PlanModuleType::values())],
            'price' => 'required|numeric|min:0',
            'recruitment_interview_limit' => 'required|integer|min:0',
            'loan_interview_limit' => 'required|integer|min:0',
            'ai_generation_limit' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $module = PlanModuleType::from($validated['module_type']);

        if ($module === PlanModuleType::Recruitment) {
            $validated['loan_interview_limit'] = 0;
        }

        if ($module === PlanModuleType::LoanApplicants) {
            $validated['recruitment_interview_limit'] = 0;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['interview_limit'] = (int) $validated['recruitment_interview_limit'];
        $validated['ai_generation_limit'] = (int) ($validated['ai_generation_limit'] ?? ($plan?->ai_generation_limit ?? 0));
        $validated['candidate_limit'] = $plan?->candidate_limit ?? 0;
        $validated['team_member_limit'] = $plan?->team_member_limit ?? 0;

        return $validated;
    }

    private function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $suffix = 2;

        while (
            Plan::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
