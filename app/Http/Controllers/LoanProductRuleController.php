<?php

namespace App\Http\Controllers;

use App\Models\LoanProductRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanProductRuleController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', LoanProductRule::class);
        $rules = LoanProductRule::latest()->paginate(10);
        // Using a basic response for now as the view is not requested in detail.
        return view('loan-rules.index', compact('rules'));
    }

    public function create()
    {
        Gate::authorize('create', LoanProductRule::class);
        return view('loan-rules.create');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', LoanProductRule::class);

        $validated = $request->validate([
            'product_type' => 'required|string',
            'interest_rate' => 'required|numeric',
            'interest_method' => 'required|string',
            'max_dbr_percentage' => 'required|numeric',
            'min_income' => 'nullable|numeric',
            'max_loan_amount' => 'nullable|numeric',
            'max_tenure_months' => 'nullable|integer',
            'max_ltv_percentage' => 'nullable|numeric',
            'version' => 'required|integer',
            'effective_date' => 'required|date',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->id;

        LoanProductRule::create($validated);

        return redirect()->route('loan-rules.index')->with('success', 'Rule created successfully.');
    }
}
