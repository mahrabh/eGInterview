<x-dark-layout>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Full Report: {{ $application->applicant->application_reference }}</h2>
        <a href="{{ route('loan-applications.index') }}" class="text-sm font-semibold text-slate-500 hover:text-indigo-600 transition">← Back to List</a>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 font-medium text-sm">
            {{ session('success') }}
        </div>
    @endif

    @php
        $shouldAutoRefreshReport = $application->status === 'processing'
            || ($application->submitted_at && !is_array($application->calculation_data));
    @endphp

    @if($shouldAutoRefreshReport)
        <div id="report-processing-banner" class="p-4 bg-sky-50 border border-sky-200 rounded-xl flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-sky-500 animate-pulse shrink-0"></span>
            <p class="text-sky-800 font-medium text-sm">
                Processing interview data… This page will refresh automatically when the full report is ready.
            </p>
        </div>
    @endif

    @php
        $displayOutcome = match ($application->outcome) {
            'Indicatively Eligible' => 'Approved',
            'Not Eligible Under Current Rules' => 'Rejected',
            'Needs Review' => 'Needs Review',
            default => null,
        };
        $assessmentReasons = collect(is_array($application->reason_codes) ? $application->reason_codes : [])
            ->filter(fn ($reason) => is_string($reason) && trim($reason) !== '')
            ->values();
        $calcSummary = is_array($application->calculation_data) ? $application->calculation_data : [];
        $eligibleAmount = $calcSummary['eligible_amount'] ?? null;
        $bindingConstraints = is_array($calcSummary['binding_constraints'] ?? null)
            ? $calcSummary['binding_constraints']
            : [];
        $extractedFieldLabels = [
            'loan_type' => 'Loan Type',
            'loan_purpose' => 'Purpose',
            'requested_amount' => 'Requested Amount',
            'requested_tenure' => 'Requested Tenure (M)',
            'income_source' => 'Income Source',
            'employer_name' => 'Employer / Business',
            'exact_monthly_income' => 'Monthly Income',
            'other_regular_monthly_income' => 'Other Monthly Income',
            'existing_monthly_obligations' => 'Existing EMI',
            'asset_value' => 'Asset Value (Car/Home)',
            'down_payment' => 'Down Payment (Car/Home)',
        ];
        $extracted = is_array($application->extracted_data) ? $application->extracted_data : [];
        $scalarFallback = [
            'loan_type' => $application->loan_type,
            'loan_purpose' => $application->purpose,
            'requested_amount' => $application->requested_amount,
            'requested_tenure' => $application->tenure_months,
            'income_source' => $application->income_source ?? $application->employment_status,
            'employer_name' => $application->employer_name,
            'exact_monthly_income' => $application->monthly_income,
            'other_regular_monthly_income' => $application->other_monthly_income,
            'existing_monthly_obligations' => $application->existing_emi,
            'asset_value' => $application->asset_value,
            'down_payment' => $application->down_payment,
        ];
        $missingExtractedFields = [];

        foreach ($extractedFieldLabels as $key => $label) {
            $fieldData = $extracted[$key] ?? [];
            $rawValue = is_array($fieldData) ? ($fieldData['value'] ?? '') : $fieldData;

            if ($rawValue === '' || $rawValue === null) {
                $rawValue = $scalarFallback[$key] ?? '';
            }

            if ($rawValue === '' || $rawValue === null) {
                $missingExtractedFields[] = $label;
            }
        }

        $needsReviewState = $displayOutcome === 'Needs Review'
            || $application->status === 'needs_review'
            || !empty($extracted['_meta']['blocking_fields'] ?? []);
        $showRetryExtraction = $application->submitted_at !== null
            && $needsReviewState
            && ($missingExtractedFields !== [] || !$application->extracted_data);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
        <div class="glass-panel rounded-2xl p-5 sm:p-6">
            <h3 class="text-base font-bold text-slate-900 mb-4">Applicant Information</h3>
            <div class="space-y-3 text-sm">
                <p><span class="text-slate-500">Name:</span> <span class="text-slate-900 font-medium">{{ $application->applicant->name }}</span></p>
                <p><span class="text-slate-500">Phone:</span> <span class="text-slate-900 font-medium">{{ $application->applicant->masked_phone ?? 'N/A' }}</span></p>
                <p><span class="text-slate-500">NID (Last 4):</span> <span class="text-slate-900 font-medium">****{{ $application->applicant->nid_last_four }}</span></p>
            </div>
        </div>

        <div class="glass-panel rounded-2xl p-5 sm:p-6 flex flex-col justify-start items-start space-y-4">
            <h3 class="text-base font-bold text-slate-900">Assessment Outcome</h3>
            @if($displayOutcome === 'Approved')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">
                    Approved
                </div>
                <div class="text-sm text-slate-600 space-y-2">
                    <p class="font-semibold text-emerald-700">Recommendation</p>
                    <p>Based on the declared income, obligations, and static loan policy, the applicant appears indicatively eligible under current rules.</p>
                    @if($eligibleAmount !== null)
                        <p>Indicative eligible amount: <span class="text-slate-900 font-semibold">{{ number_format((float) $eligibleAmount, 2) }} BDT</span>.</p>
                    @endif
                    <p class="text-slate-500 text-xs">Final approval requires document verification and authorized bank officer review.</p>
                </div>
            @elseif($displayOutcome === 'Rejected')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-semibold border bg-rose-50 text-rose-700 border-rose-200">
                    Rejected
                </div>
                <div class="text-sm text-slate-600 space-y-2 w-full">
                    <p class="font-semibold text-rose-700">Reasoning</p>
                    @if($assessmentReasons->isNotEmpty())
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($assessmentReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p>The requested loan does not meet one or more indicative policy checks (affordability, DBR, LTV, income, or loan amount limits).</p>
                    @endif
                    @if($bindingConstraints !== [])
                        <p class="text-slate-500">Primary limiting factor: <span class="text-slate-900 font-medium">{{ implode(', ', array_map(static fn ($item) => str_replace('_', ' ', (string) $item), $bindingConstraints)) }}</span>.</p>
                    @endif
                    @if($eligibleAmount !== null)
                        <p>Indicative eligible amount under current rules: <span class="text-slate-900 font-semibold">{{ number_format((float) $eligibleAmount, 2) }} BDT</span>.</p>
                    @endif
                </div>
            @elseif($displayOutcome === 'Needs Review' || $application->status === 'needs_review')
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex px-3 py-1 rounded-full text-sm font-semibold border bg-amber-50 text-amber-700 border-amber-200">
                        Needs Review
                    </div>
                    @if($showRetryExtraction)
                        <form action="{{ route('loan-applications.retry-extraction', $application->id) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold transition-colors"
                                title="Re-run automatic extraction from the saved transcript"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Re-extract
                            </button>
                        </form>
                    @endif
                </div>
                <div class="text-sm text-slate-600 space-y-2 w-full">
                    <p class="font-semibold text-amber-700">Recommendation</p>
                    @if($assessmentReasons->isNotEmpty())
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($assessmentReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p>Some required application data is missing, unclear, or could not be confirmed automatically. Please review the extracted fields and transcript before making a decision.</p>
                    @endif
                    @if($showRetryExtraction && $missingExtractedFields !== [])
                        <p class="text-xs text-amber-700">
                            Missing fields detected: {{ implode(', ', $missingExtractedFields) }}. Use Re-extract to try automatic extraction again.
                        </p>
                    @endif
                </div>
            @elseif($application->status === 'draft')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-semibold border bg-slate-100 text-slate-600 border-slate-200">
                    Draft
                </div>
            @else
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-semibold border bg-sky-50 text-sky-700 border-sky-200">
                    Processing
                </div>
            @endif
        </div>
    </div>

    @if($showRetryExtraction && $application->status === 'processing' && !$application->extracted_data)
        <div class="glass-panel rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-1">Processing Incomplete</h3>
                <p class="text-sm text-slate-500">Automatic processing could not extract application data yet.</p>
            </div>
            <form action="{{ route('loan-applications.retry-extraction', $application->id) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-semibold text-sm transition-colors whitespace-nowrap">
                    Retry Processing
                </button>
            </form>
        </div>
    @endif

    <div class="glass-panel rounded-2xl p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div>
                <h3 class="text-base font-bold text-slate-900">Extracted Data & Recalculate</h3>
                <p class="text-sm text-slate-500 mt-1">Modify the extracted values below to recalculate the eligibility.</p>
            </div>
            @if($showRetryExtraction)
                <form action="{{ route('loan-applications.retry-extraction', $application->id) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold transition-colors whitespace-nowrap"
                        title="Re-run automatic extraction from the saved transcript"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Re-extract from Transcript
                    </button>
                </form>
            @endif
        </div>

        <form action="{{ route('loan-applications.recalculate', $application->id) }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 mb-6">
                @php
                    $fields = $extractedFieldLabels;
                @endphp

                @foreach($fields as $key => $label)
                    @php
                        $fieldData = $extracted[$key] ?? [];
                        $rawValue = is_array($fieldData) ? ($fieldData['value'] ?? '') : $fieldData;
                        if ($rawValue === '' || $rawValue === null) {
                            $rawValue = $scalarFallback[$key] ?? '';
                        }
                        $value = is_scalar($rawValue) ? $rawValue : '';
                        $isFilled = $value !== '' && $value !== null;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-semibold text-slate-700">{{ $label }}</label>
                            <span class="inline-flex w-2.5 h-2.5 rounded-full shrink-0 {{ $isFilled ? 'bg-emerald-500' : 'bg-rose-500' }}" title="{{ $isFilled ? 'Filled' : 'Missing' }}"></span>
                        </div>
                        <input type="text" name="extracted_data[{{ $key }}][value]" value="{{ $value }}" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-colors">
                    </div>
                @endforeach
            </div>
            <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-semibold text-sm transition-colors">
                Save & Recalculate
            </button>
        </form>
    </div>

    @if($application->calculation_data)
        @php
            $calculationData = $application->calculation_data;
            $orderedCalculationKeys = [
                'policy_version',
                'policy_classification',
                'loan_type',
                'total_monthly_income',
                'existing_monthly_obligations',
                'max_allowed_total_emi',
                'available_new_emi',
                'requested_amount',
                'requested_tenure_months',
                'annual_interest_rate_percentage',
                'interest_method',
                'proposed_emi',
                'projected_dbr_percentage',
                'total_repayment',
                'eligible_amount',
                'asset_value',
                'declared_down_payment',
                'ltv_limit',
                'ltv_percentage',
                'binding_constraints',
                'current_dbr_percentage',
                'affordable_principal',
                'minimum_required_down_payment',
                'total_interest',
                'indicative_max_loan_amount',
            ];
            $renderedCalculationKeys = [];
            $formatCalculationValue = static function (string $key, mixed $val): string {
                if (is_array($val)) {
                    return implode(', ', array_map(
                        static fn ($item) => is_scalar($item) ? (string) $item : json_encode($item),
                        $val
                    ));
                }

                if ($val === null || $val === '') {
                    return 'N/A';
                }

                if ($key === 'policy_classification' && is_string($val)) {
                    return ucwords(str_replace('_', ' ', $val));
                }

                if (in_array($key, ['annual_interest_rate_percentage', 'projected_dbr_percentage', 'current_dbr_percentage', 'ltv_percentage'], true)) {
                    return number_format((float) $val, 2) . '%';
                }

                if (is_numeric($val)) {
                    return number_format((float) $val, 2);
                }

                return (string) $val;
            };
            $formatCalculationLabel = static function (string $key): string {
                return ucwords(str_replace('_', ' ', $key));
            };
        @endphp
        <div class="glass-panel rounded-2xl p-5 sm:p-6">
            <h3 class="text-base font-bold text-slate-900 mb-4">Calculations</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-5">
                @foreach($orderedCalculationKeys as $key)
                    @if(array_key_exists($key, $calculationData))
                        @php $renderedCalculationKeys[] = $key; @endphp
                        <div class="min-w-0 rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide break-words">{{ $formatCalculationLabel($key) }}</p>
                            <p class="text-sm md:text-base font-bold text-slate-900 mt-1 break-words leading-snug">{{ $formatCalculationValue($key, $calculationData[$key]) }}</p>
                        </div>
                    @endif
                @endforeach
                @foreach($calculationData as $key => $val)
                    @if($key !== 'rule_snapshot' && !in_array($key, $renderedCalculationKeys, true))
                        <div class="min-w-0 rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                            <p class="text-xs text-slate-500 font-medium uppercase tracking-wide break-words">{{ $formatCalculationLabel($key) }}</p>
                            <p class="text-sm md:text-base font-bold text-slate-900 mt-1 break-words leading-snug">{{ $formatCalculationValue($key, $val) }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        @if(isset($calculationData['rule_snapshot']))
        @php
            $ruleSnapshot = $calculationData['rule_snapshot'];
            $orderedRuleKeys = [
                'name',
                'max_dbr_percentage',
                'interest_rate',
                'interest_rate_type',
                'interest_method',
                'min_tenure_months',
                'max_tenure_months',
                'min_income',
                'min_loan_amount',
                'max_loan_amount',
                'security',
                'max_ltv_percentage',
                'ltv_basis',
                'policy_basis',
                'regulatory_reference',
                'version',
            ];
            $renderedRuleKeys = [];
        @endphp
        <div class="glass-panel rounded-2xl p-5 sm:p-6">
            <h3 class="text-base font-bold text-slate-900 mb-4">Applied Rule Snapshot (Version {{ $ruleSnapshot['version'] ?? '?' }})</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                @foreach($orderedRuleKeys as $key)
                    @if(array_key_exists($key, $ruleSnapshot) && !in_array($key, ['id', 'created_at', 'updated_at', 'created_by'], true))
                        @php $renderedRuleKeys[] = $key; @endphp
                        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                            <p class="text-xs text-slate-500 font-medium">{{ str_replace('_', ' ', Str::title($key)) }}</p>
                            <p class="text-slate-900 font-semibold mt-1 break-words">{{ $ruleSnapshot[$key] ?? 'N/A' }}</p>
                        </div>
                    @endif
                @endforeach
                @foreach($ruleSnapshot as $key => $val)
                    @if(!in_array($key, ['id', 'created_at', 'updated_at', 'created_by'], true) && !in_array($key, $renderedRuleKeys, true))
                        <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                            <p class="text-xs text-slate-500 font-medium">{{ str_replace('_', ' ', Str::title($key)) }}</p>
                            <p class="text-slate-900 font-semibold mt-1 break-words">{{ $val }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    @endif

    @php
        $transcriptBody = $application->transcript_text ?: $application->transcript;
    @endphp
    <div class="glass-panel rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-bold text-slate-900">Interview Transcript</h3>
            <p class="text-sm text-slate-500 mt-1">Review the full unedited conversation between the applicant and the AI.</p>
        </div>
        <button
            type="button"
            onclick="showTranscript(@js($application->applicant->name), @js($transcriptBody ?? ''))"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-semibold text-sm transition-colors whitespace-nowrap"
        >
            View Transcript
        </button>
    </div>

    <div class="p-4 bg-sky-50 border border-sky-200 rounded-xl">
        <p class="text-sky-800 font-medium text-center text-sm">
            Notice: This assessment uses self-declared information and is subject to document verification and final bank approval.
        </p>
    </div>
</div>

@if($shouldAutoRefreshReport)
<script>
(function () {
    const statusUrl = @json(route('loan-applications.status', $application->id));
    let attempts = 0;
    const maxAttempts = 90;

    const poll = async function () {
        if (attempts >= maxAttempts) {
            return;
        }

        attempts++;

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.is_report_ready || (data.status !== 'processing' && data.has_extracted_data)) {
                window.location.reload();
            }
        } catch (error) {
            // Ignore transient network errors and keep polling.
        }
    };

    poll();
    window.setInterval(poll, 2000);
})();
</script>
@endif
</x-dark-layout>
