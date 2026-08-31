<x-dark-layout>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Full Report: {{ $application->applicant->application_reference }}</h2>
        <a href="{{ route('loan-applications.index') }}" class="text-gray-400 hover:text-white transition">Back to List</a>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/50 rounded-xl text-emerald-400 font-medium">
            {{ session('success') }}
        </div>
    @endif

    @php
        $shouldAutoRefreshReport = $application->status === 'processing'
            || ($application->submitted_at && !is_array($application->calculation_data));
    @endphp

    @if($shouldAutoRefreshReport)
        <div id="report-processing-banner" class="p-4 bg-blue-500/10 border border-blue-500/50 rounded-xl flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-400 animate-pulse shrink-0"></span>
            <p class="text-blue-300 font-medium text-sm">
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
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Applicant Information -->
        <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6">
            <h3 class="text-lg font-bold text-white mb-4">Applicant Information</h3>
            <div class="space-y-3 text-sm">
                <p><span class="text-gray-400">Name:</span> <span class="text-white">{{ $application->applicant->name }}</span></p>
                <p><span class="text-gray-400">Phone:</span> <span class="text-white">{{ $application->applicant->phone }}</span></p>
                <p><span class="text-gray-400">NID (Last 4):</span> <span class="text-white">****{{ $application->applicant->nid_last_four }}</span></p>
            </div>
        </div>

        <!-- Outcome -->
        <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6 flex flex-col justify-center items-start">
            <h3 class="text-lg font-bold text-white mb-4">Assessment Outcome</h3>
            @if($displayOutcome === 'Approved')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-medium border bg-emerald-500/10 text-emerald-400 border-emerald-500/20">
                    Approved
                </div>
            @elseif($displayOutcome === 'Rejected')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-medium border bg-rose-500/10 text-rose-400 border-rose-500/20">
                    Rejected
                </div>
            @elseif($displayOutcome === 'Needs Review' || $application->status === 'needs_review' || is_array($application->extracted_data))
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-medium border bg-amber-500/10 text-amber-400 border-amber-500/20">
                    Needs Review
                </div>
            @elseif($application->status === 'draft')
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-medium border bg-slate-500/10 text-slate-400 border-slate-500/20">
                    Draft
                </div>
            @else
                <div class="inline-flex px-3 py-1 rounded-full text-sm font-medium border bg-blue-500/10 text-blue-400 border-blue-500/20">
                    Processing
                </div>
            @endif
        </div>
    </div>

    @if(!$application->extracted_data && in_array($application->status, ['needs_review', 'processing'], true))
        <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white mb-2">Processing Incomplete</h3>
                <p class="text-sm text-gray-400">Automatic processing could not extract application data yet.</p>
            </div>
            <form action="{{ route('loan-applications.retry-extraction', $application->id) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-medium transition-colors whitespace-nowrap">
                    Retry Processing
                </button>
            </form>
        </div>
    @endif

    <!-- Edit Extracted Values -->
    <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6">
        <h3 class="text-lg font-bold text-white mb-4">Extracted Data & Recalculate</h3>
        <p class="text-sm text-gray-400 mb-6">Modify the extracted values below to recalculate the eligibility.</p>
        
        <form action="{{ route('loan-applications.recalculate', $application->id) }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                @php
                    $fields = [
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
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-400">{{ $label }}</label>
                            <span class="inline-flex w-2.5 h-2.5 rounded-full shrink-0 {{ $isFilled ? 'bg-emerald-500' : 'bg-rose-500' }}" title="{{ $isFilled ? 'Filled' : 'Missing' }}"></span>
                        </div>
                        <input type="text" name="extracted_data[{{ $key }}][value]" value="{{ $value }}" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none">
                    </div>
                @endforeach
            </div>
            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-medium transition-colors">
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

                if (in_array($key, ['annual_interest_rate_percentage', 'projected_dbr_percentage', 'current_dbr_percentage', 'ltv_percentage'], true)) {
                    return number_format((float) $val, 2) . '%';
                }

                if (is_numeric($val)) {
                    return number_format((float) $val, 2);
                }

                return (string) $val;
            };
        @endphp
        <!-- Calculation Details -->
        <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6">
            <h3 class="text-lg font-bold text-white mb-4">Calculations</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($orderedCalculationKeys as $key)
                    @if(array_key_exists($key, $calculationData))
                        @php $renderedCalculationKeys[] = $key; @endphp
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="text-lg font-bold text-white mt-1">{{ $formatCalculationValue($key, $calculationData[$key]) }}</p>
                        </div>
                    @endif
                @endforeach
                @foreach($calculationData as $key => $val)
                    @if($key !== 'rule_snapshot' && !in_array($key, $renderedCalculationKeys, true))
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="text-lg font-bold text-white mt-1">{{ $formatCalculationValue($key, $val) }}</p>
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
        <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6">
            <h3 class="text-lg font-bold text-white mb-4">Applied Rule Snapshot (Version {{ $ruleSnapshot['version'] ?? '?' }})</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
                @foreach($orderedRuleKeys as $key)
                    @if(array_key_exists($key, $ruleSnapshot) && !in_array($key, ['id', 'created_at', 'updated_at', 'created_by'], true))
                        @php $renderedRuleKeys[] = $key; @endphp
                        <div>
                            <p class="text-gray-400">{{ str_replace('_', ' ', Str::title($key)) }}</p>
                            <p class="text-white">{{ $ruleSnapshot[$key] ?? 'N/A' }}</p>
                        </div>
                    @endif
                @endforeach
                @foreach($ruleSnapshot as $key => $val)
                    @if(!in_array($key, ['id', 'created_at', 'updated_at', 'created_by'], true) && !in_array($key, $renderedRuleKeys, true))
                        <div>
                            <p class="text-gray-400">{{ str_replace('_', ' ', Str::title($key)) }}</p>
                            <p class="text-white">{{ $val }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    @endif

    <!-- Transcript Modal (uses shared showTranscript() from dark-layout) -->
    @php
        $transcriptBody = $application->transcript_text ?: $application->transcript;
    @endphp
    <div class="bg-gray-800 rounded-xl border border-gray-700/50 p-6 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-white">Interview Transcript</h3>
            <p class="text-sm text-gray-400">Review the full unedited conversation between the applicant and the AI.</p>
        </div>
        <button
            type="button"
            onclick="showTranscript(@js($application->applicant->name), @js($transcriptBody ?? ''))"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-medium transition-colors whitespace-nowrap"
        >
            View Transcript
        </button>
    </div>

    <div class="p-4 bg-blue-500/10 border border-blue-500/50 rounded-xl">
        <p class="text-blue-400 font-medium text-center">
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
