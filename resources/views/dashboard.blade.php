<x-dark-layout>
    @php
        $mode = $dashboardMode ?? 'admin';
        $subtitle = match ($mode) {
            'recruiter' => 'Your recruitment pipeline at a glance.',
            'analyst' => 'Your loan applications and review queue.',
            default => 'System overview across recruitment and loan interviews.',
        };
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Dashboard</h2>
            <p class="text-slate-400 mt-1.5 text-sm">{{ $subtitle }}</p>
        </div>
        @if($mode === 'recruiter')
            <a href="{{ route('recruitment.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold transition-colors shrink-0">
                Open Recruitment
            </a>
        @elseif($mode === 'analyst')
            <a href="{{ route('loan-applications.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold transition-colors shrink-0">
                Open Loan Applicants
            </a>
        @endif
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 {{ ($showRecruitment && $showLoans) ? 'xl:grid-cols-4' : 'md:grid-cols-3' }} gap-3 mb-6">
        @if($showRecruitment)
            <div class="glass-panel rounded-2xl border border-slate-800 px-5 py-4">
                <p class="text-xs font-semibold text-slate-400">Candidates</p>
                <p class="text-2xl md:text-3xl font-black text-white mt-1 tabular-nums">{{ number_format($totalCandidates) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $mode === 'admin' ? 'All candidates' : 'Assigned to you' }}</p>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl border border-slate-800 px-5 py-4">
                <p class="text-xs font-semibold text-slate-400">Loan Applicants</p>
                <p class="text-2xl md:text-3xl font-black text-white mt-1 tabular-nums">{{ number_format($totalLoanApplicants) }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $mode === 'admin' ? 'All applicants' : 'Assigned to you' }}</p>
            </div>
        @endif

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl border border-slate-800 px-5 py-4">
                <p class="text-xs font-semibold text-slate-400">Completed</p>
                <p class="text-2xl md:text-3xl font-black text-emerald-300 mt-1 tabular-nums">{{ number_format($completedInterviews) }}</p>
                <p class="text-xs text-slate-500 mt-1">Interviews finished</p>
            </div>
        @endif

        @if($showLoans && $mode === 'analyst')
            <div class="glass-panel rounded-2xl border border-amber-500/20 bg-amber-500/5 px-5 py-4">
                <p class="text-xs font-semibold text-amber-300/90">Needs Review</p>
                <p class="text-2xl md:text-3xl font-black text-amber-300 mt-1 tabular-nums">{{ number_format($loanNeedsReview) }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting your action</p>
            </div>
        @elseif(!($showLoans && $mode === 'analyst'))
            <div class="glass-panel rounded-2xl border border-slate-800 px-5 py-4">
                <p class="text-xs font-semibold text-slate-400">Needs Attention</p>
                <p class="text-2xl md:text-3xl font-black text-white mt-1 tabular-nums">{{ number_format($needsAttention) }}</p>
                <p class="text-xs text-slate-500 mt-1">
                    @if($showRecruitment && $showLoans)
                        Pending · expired · review
                    @elseif($showRecruitment)
                        Pending · expired links
                    @else
                        Review · processing
                    @endif
                </p>
            </div>
        @endif
    </div>

    {{-- Status + lists --}}
    <div class="grid grid-cols-1 {{ ($showRecruitment && $showLoans) ? 'xl:grid-cols-2' : 'xl:grid-cols-5' }} gap-5">

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden {{ $showLoans ? '' : 'xl:col-span-2' }}">
                <div class="px-5 py-4 border-b border-slate-800/70 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-200">Recruitment Status</h3>
                    <a href="{{ route('recruitment.index') }}" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300">View all</a>
                </div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                        <p class="text-xs text-slate-400 font-medium">Draft</p>
                        <p class="text-xl font-black text-white mt-0.5 tabular-nums">{{ number_format($draftCandidates) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                        <p class="text-xs text-slate-400 font-medium">Pending</p>
                        <p class="text-xl font-black text-amber-300 mt-0.5 tabular-nums">{{ number_format($pendingApproval) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                        <p class="text-xs text-slate-400 font-medium">Active Links</p>
                        <p class="text-xl font-black text-indigo-300 mt-0.5 tabular-nums">{{ number_format($activeLinks) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                        <p class="text-xs text-slate-400 font-medium">Expired</p>
                        <p class="text-xl font-black text-rose-300 mt-0.5 tabular-nums">{{ number_format($expiredLinks) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3 sm:col-span-2">
                        <p class="text-xs text-slate-400 font-medium">Completed</p>
                        <p class="text-xl font-black text-emerald-300 mt-0.5 tabular-nums">{{ number_format($completedInterviews) }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden {{ $showRecruitment ? '' : 'xl:col-span-2' }}">
                <div class="px-5 py-4 border-b border-slate-800/70 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-200">Loan Pipeline</h3>
                    <a href="{{ route('loan-applications.index') }}" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300">View all</a>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-2.5">
                        <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                            <p class="text-xs text-slate-400 font-medium">Draft</p>
                            <p class="text-xl font-black text-white mt-0.5 tabular-nums">{{ number_format((int) ($loanStatusCounts['draft'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                            <p class="text-xs text-slate-400 font-medium">Needs Review</p>
                            <p class="text-xl font-black text-amber-300 mt-0.5 tabular-nums">{{ number_format($loanNeedsReview) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                            <p class="text-xs text-slate-400 font-medium">Processing</p>
                            <p class="text-xl font-black text-sky-300 mt-0.5 tabular-nums">{{ number_format($loanProcessing) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-900/80 border border-slate-800 px-3.5 py-3">
                            <p class="text-xs text-slate-400 font-medium">Assessed</p>
                            <p class="text-xl font-black text-indigo-300 mt-0.5 tabular-nums">{{ number_format((int) ($loanStatusCounts['assessed'] ?? 0)) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2.5">
                        <div class="rounded-xl bg-emerald-500/5 border border-emerald-500/20 px-3 py-2.5">
                            <p class="text-xs font-medium text-emerald-400/90">Eligible</p>
                            <p class="text-lg font-black text-emerald-300 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Indicatively Eligible'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-rose-500/5 border border-rose-500/20 px-3 py-2.5">
                            <p class="text-xs font-medium text-rose-400/90">Not Eligible</p>
                            <p class="text-lg font-black text-rose-300 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Not Eligible Under Current Rules'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-500/5 border border-amber-500/20 px-3 py-2.5">
                            <p class="text-xs font-medium text-amber-400/90">Review</p>
                            <p class="text-lg font-black text-amber-300 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Needs Review'] ?? 0)) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden {{ $showLoans ? '' : 'xl:col-span-3' }}">
                <div class="px-5 py-4 border-b border-slate-800/70 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-200">Recent Recruitment</h3>
                    <a href="{{ route('recruitment.index') }}" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300">Manage</a>
                </div>
                <div class="divide-y divide-slate-800/60">
                    @forelse($recentInterviews as $interview)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-900/50 transition-colors">
                            <div class="min-w-0 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($interview->candidate_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-white truncate">{{ $interview->candidate_name }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 truncate">{{ $interview->applied_role }} · {{ ucfirst($interview->status) }}</p>
                                </div>
                            </div>
                            @if($interview->status === 'completed')
                                <span class="text-xs font-semibold text-emerald-400 whitespace-nowrap">Completed</span>
                            @else
                                <a href="{{ route('recruitment.index') }}" class="text-xs font-semibold text-slate-400 hover:text-indigo-300 whitespace-nowrap">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-slate-500">No recruitment activity yet.</div>
                    @endforelse
                </div>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden {{ $showRecruitment ? '' : ($mode === 'analyst' ? 'xl:col-span-3' : 'xl:col-span-3') }}">
                <div class="px-5 py-4 border-b border-slate-800/70 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-200">
                        {{ $mode === 'analyst' ? 'Recent Applications' : 'Recent Loan Activity' }}
                    </h3>
                    <a href="{{ route('loan-applications.index') }}" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300">Manage</a>
                </div>
                <div class="divide-y divide-slate-800/60">
                    @forelse($recentLoans as $loan)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-900/50 transition-colors">
                            <div class="min-w-0 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($loan->applicant->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-white truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 truncate">
                                        {{ $loan->applicant->application_reference ?? '—' }}
                                        · {{ ucfirst(str_replace('_', ' ', $loan->status ?? 'draft')) }}
                                        @if($loan->outcome)
                                            · {{ $loan->outcome }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @php
                                $canOpenReport = $loan->submitted_at !== null
                                    || in_array($loan->status, ['pending', 'needs_review', 'assessed', 'processing'], true)
                                    || is_array($loan->extracted_data);
                            @endphp
                            @if($canOpenReport)
                                <a href="{{ route('loan-applications.report', $loan->id) }}" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 whitespace-nowrap">Report</a>
                            @else
                                <a href="{{ route('loan-applications.index') }}" class="text-xs font-semibold text-slate-400 hover:text-indigo-300 whitespace-nowrap">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-slate-500">No loan activity yet.</div>
                    @endforelse
                </div>
            </div>

            @if($mode === 'analyst')
                <div class="glass-panel rounded-2xl border border-amber-500/15 overflow-hidden xl:col-span-5">
                    <div class="px-5 py-4 border-b border-slate-800/70 flex items-center justify-between bg-amber-500/[0.03]">
                        <h3 class="text-sm font-bold text-amber-300">Needs Review Queue</h3>
                        <a href="{{ route('loan-applications.index', ['status' => 'needs_review']) }}" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300">View queue</a>
                    </div>
                    <div class="divide-y divide-slate-800/60">
                        @forelse($needsReviewLoans as $loan)
                            <div class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-900/50 transition-colors">
                                <div class="min-w-0 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-300 text-sm font-bold shrink-0">
                                        {{ strtoupper(substr($loan->applicant->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-slate-500 mt-0.5 truncate">
                                            {{ $loan->applicant->application_reference ?? '—' }}
                                            @if($loan->outcome)
                                                · {{ $loan->outcome }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('loan-applications.report', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs font-semibold text-amber-300 hover:bg-amber-500/20 whitespace-nowrap transition-colors">Review</a>
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-slate-500">No items need review right now.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-dark-layout>
