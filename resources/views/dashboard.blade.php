<x-dark-layout>
    @php
        $mode = $dashboardMode ?? 'admin';
        $subtitle = match ($mode) {
            'recruiter' => 'Run AI interviews, review candidates, and keep your hiring pipeline moving.',
            'analyst' => 'AI loan interviews, assessments, and review queue — ready for action.',
            'both' => 'Run recruitment interviews and loan assessments from one workspace.',
            default => 'Intelligent interviews for recruitment and loan assessment — live, scored, and ready to review.',
        };
        $heroEyebrow = match ($mode) {
            'recruiter' => 'Recruitment intelligence',
            'analyst' => 'Loan assessment intelligence',
            'both' => 'Combined interview workspace',
            default => 'AI interview workspace',
        };
    @endphp

    {{-- AI Interview hero (light) --}}
    <section class="ai-hero rounded-2xl sm:rounded-3xl px-5 sm:px-8 py-7 sm:py-9 {{ $mode === 'analyst' ? 'mb-4' : 'mb-6 sm:mb-8' }} text-slate-900">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
            <div class="max-w-2xl">
                <div class="ai-hero-badge ai-hero-rise inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-[11px] font-semibold uppercase tracking-[0.14em] text-indigo-600 mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $heroEyebrow }}
                </div>
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-black tracking-tight leading-tight text-slate-900 min-h-[1.2em]">
                    @php
                        $welcomeLine = 'Welcome back' . (Auth::user()?->name ? ', ' . explode(' ', Auth::user()->name)[0] : '') . '.';
                    @endphp
                    <span id="welcome-type-text" data-welcome="{{ $welcomeLine }}">{{ $welcomeLine }}</span><span id="welcome-type-caret" class="welcome-type-caret" aria-hidden="true">|</span>
                </h2>
                <p class="ai-hero-rise-2 mt-3 text-sm sm:text-base text-slate-500 leading-relaxed">
                    {{ $subtitle }}
                </p>
                <div class="ai-hero-rise-3 mt-5 flex flex-wrap gap-2">
                    @if($showRecruitment)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 border border-slate-200 text-xs font-medium text-slate-700">
                            <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                            Live AI interviews
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 border border-slate-200 text-xs font-medium text-slate-700">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            Auto evaluations
                        </span>
                    @endif
                    @if($showLoans)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 border border-slate-200 text-xs font-medium text-slate-700">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Policy-based scoring
                        </span>
                    @endif
                </div>
            </div>

            <div class="ai-hero-rise-3 flex flex-wrap items-center gap-2 shrink-0">
                @if($mode === 'recruiter' || ($showRecruitment && !$showLoans))
                    <a href="{{ route('recruitment.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 text-sm font-bold transition-colors shadow-sm shadow-indigo-500/20">
                        Open Recruitment
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                @elseif($mode === 'analyst' || ($showLoans && !$showRecruitment))
                    <a href="{{ route('loan-applications.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 text-sm font-bold transition-colors shadow-sm shadow-indigo-500/20">
                        Open Loan Applicants
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </a>
                @else
                    <a href="{{ route('recruitment.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-500 text-sm font-bold transition-colors shadow-sm shadow-indigo-500/20">
                        Recruitment
                    </a>
                    <a href="{{ route('loan-applications.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-indigo-200 hover:text-indigo-700 text-sm font-semibold transition-colors">
                        Loan Applicants
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if($mode === 'analyst')
        {{-- Analyst workspace: balanced KPIs + focused loan layout --}}
        @php
            $analystAssessed = (int) ($loanStatusCounts['assessed'] ?? 0);
            $analystEligible = (int) ($loanOutcomeCounts['Indicatively Eligible'] ?? 0);
            $analystNotEligible = (int) ($loanOutcomeCounts['Not Eligible Under Current Rules'] ?? 0);
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="glass-panel rounded-xl px-4 py-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Applicants</p>
                        <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($totalLoanApplicants) }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Assigned to you</p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-sky-50 border border-sky-100 text-sky-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>
            </div>
            <div class="glass-panel rounded-xl border-amber-200 bg-amber-50/70 px-4 py-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold text-amber-700 uppercase tracking-wide">Needs Review</p>
                        <p class="text-2xl font-black text-amber-700 mt-1 tabular-nums">{{ number_format($loanNeedsReview) }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Awaiting action</p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>
            <div class="glass-panel rounded-xl px-4 py-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Assessed</p>
                        <p class="text-2xl font-black text-indigo-700 mt-1 tabular-nums">{{ number_format($analystAssessed) }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Completed scoring</p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </div>
                </div>
            </div>
            <div class="glass-panel rounded-xl px-4 py-3.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Eligible</p>
                        <p class="text-2xl font-black text-emerald-700 mt-1 tabular-nums">{{ number_format($analystEligible) }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ number_format($analystNotEligible) }} not eligible</p>
                    </div>
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
            <div class="glass-panel rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-sky-50 border border-sky-100 text-sky-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Loan Pipeline</h3>
                    </div>
                    <a href="{{ route('loan-applications.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">View all</a>
                </div>
                <div class="p-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5">
                        <p class="text-[11px] text-slate-500 font-medium">Draft</p>
                        <p class="text-lg font-black text-slate-900 mt-0.5 tabular-nums">{{ number_format((int) ($loanStatusCounts['draft'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 border border-amber-100 px-3 py-2.5">
                        <p class="text-[11px] text-amber-700 font-medium">Needs Review</p>
                        <p class="text-lg font-black text-amber-700 mt-0.5 tabular-nums">{{ number_format($loanNeedsReview) }}</p>
                    </div>
                    <div class="rounded-lg bg-sky-50 border border-sky-100 px-3 py-2.5">
                        <p class="text-[11px] text-sky-700 font-medium">Processing</p>
                        <p class="text-lg font-black text-sky-700 mt-0.5 tabular-nums">{{ number_format($loanProcessing) }}</p>
                    </div>
                    <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-3 py-2.5">
                        <p class="text-[11px] text-indigo-700 font-medium">Assessed</p>
                        <p class="text-lg font-black text-indigo-700 mt-0.5 tabular-nums">{{ number_format($analystAssessed) }}</p>
                    </div>
                </div>
            </div>

            <div class="glass-panel rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <h3 class="text-sm font-bold text-slate-900">Recent Applications</h3>
                    <a href="{{ route('loan-applications.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Manage</a>
                </div>
                <div class="divide-y divide-slate-100 max-h-[220px] overflow-y-auto">
                    @forelse($recentLoans as $loan)
                        <div class="px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50/80 transition-colors">
                            <div class="min-w-0 flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-700 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($loan->applicant->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
                                    <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                        {{ $loan->applicant->application_reference ?? '—' }}
                                        · {{ ucfirst(str_replace('_', ' ', $loan->status ?? 'draft')) }}
                                    </p>
                                </div>
                            </div>
                            @php
                                $canOpenReport = $loan->submitted_at !== null
                                    || in_array($loan->status, ['pending', 'needs_review', 'assessed', 'processing'], true)
                                    || is_array($loan->extracted_data);
                            @endphp
                            @if($canOpenReport)
                                <a href="{{ route('loan-applications.report', $loan->id) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 whitespace-nowrap">Report</a>
                            @else
                                <a href="{{ route('loan-applications.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 whitespace-nowrap">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-slate-500">No loan activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="glass-panel rounded-xl border-amber-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-amber-100 flex items-center justify-between bg-amber-50/90">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-sm font-bold text-amber-900">Needs Review Queue</h3>
                </div>
                <a href="{{ route('loan-applications.index', ['status' => 'needs_review']) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">View queue</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($needsReviewLoans as $loan)
                    <div class="px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors">
                        <div class="min-w-0 flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs font-bold shrink-0">
                                {{ strtoupper(substr($loan->applicant->name ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5 truncate">
                                    {{ $loan->applicant->application_reference ?? '—' }}
                                    @if($loan->outcome)
                                        · {{ $loan->outcome }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('loan-applications.report', $loan->id) }}" class="inline-flex items-center px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-800 hover:bg-amber-100 whitespace-nowrap transition-colors">Review</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">No items need review right now.</div>
                @endforelse
            </div>
        </div>
    @else
    {{-- KPI strip --}}
    <div class="grid grid-cols-2 {{ ($showRecruitment && $showLoans) ? 'xl:grid-cols-4' : 'md:grid-cols-3' }} gap-3 sm:gap-4 mb-6">
        @if($showRecruitment)
            <div class="glass-panel rounded-2xl px-5 py-4 group hover:border-indigo-200 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Candidates</p>
                        <p class="text-2xl md:text-3xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($totalCandidates) }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $mode === 'admin' ? 'All candidates' : 'Assigned to you' }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                </div>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl px-5 py-4 group hover:border-indigo-200 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Loan Applicants</p>
                        <p class="text-2xl md:text-3xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($totalLoanApplicants) }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $mode === 'admin' ? 'All applicants' : 'Assigned to you' }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-sky-50 border border-sky-100 text-sky-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                </div>
            </div>
        @endif

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl px-5 py-4 group hover:border-emerald-200 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Completed</p>
                        <p class="text-2xl md:text-3xl font-black text-emerald-600 mt-1 tabular-nums">{{ number_format($completedInterviews) }}</p>
                        <p class="text-xs text-slate-500 mt-1">AI interviews finished</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
            </div>
        @endif

        <div class="glass-panel rounded-2xl px-5 py-4 group hover:border-rose-200 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Needs Attention</p>
                    <p class="text-2xl md:text-3xl font-black text-slate-900 mt-1 tabular-nums">{{ number_format($needsAttention) }}</p>
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
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Status + lists --}}
    <div class="grid grid-cols-1 {{ ($showRecruitment && $showLoans) ? 'xl:grid-cols-2' : 'xl:grid-cols-5' }} gap-4 sm:gap-5">

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl overflow-hidden {{ $showLoans ? '' : 'xl:col-span-2' }}">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Recruitment Pipeline</h3>
                    </div>
                    <a href="{{ route('recruitment.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View all</a>
                </div>
                <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <div class="rounded-xl bg-slate-50 border border-slate-200 px-3.5 py-3">
                        <p class="text-xs text-slate-500 font-medium">Draft</p>
                        <p class="text-xl font-black text-slate-900 mt-0.5 tabular-nums">{{ number_format($draftCandidates) }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-100 px-3.5 py-3">
                        <p class="text-xs text-amber-700 font-medium">Pending</p>
                        <p class="text-xl font-black text-amber-700 mt-0.5 tabular-nums">{{ number_format($pendingApproval) }}</p>
                    </div>
                    <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-3.5 py-3">
                        <p class="text-xs text-indigo-700 font-medium">Active Links</p>
                        <p class="text-xl font-black text-indigo-700 mt-0.5 tabular-nums">{{ number_format($activeLinks) }}</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 border border-rose-100 px-3.5 py-3">
                        <p class="text-xs text-rose-700 font-medium">Expired</p>
                        <p class="text-xl font-black text-rose-700 mt-0.5 tabular-nums">{{ number_format($expiredLinks) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3.5 py-3 sm:col-span-2">
                        <p class="text-xs text-emerald-700 font-medium">Completed</p>
                        <p class="text-xl font-black text-emerald-700 mt-0.5 tabular-nums">{{ number_format($completedInterviews) }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl overflow-hidden {{ $showRecruitment ? '' : 'xl:col-span-2' }}">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 border border-sky-100 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Loan Pipeline</h3>
                    </div>
                    <a href="{{ route('loan-applications.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View all</a>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-2.5">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 px-3.5 py-3">
                            <p class="text-xs text-slate-500 font-medium">Draft</p>
                            <p class="text-xl font-black text-slate-900 mt-0.5 tabular-nums">{{ number_format((int) ($loanStatusCounts['draft'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-100 px-3.5 py-3">
                            <p class="text-xs text-amber-700 font-medium">Needs Review</p>
                            <p class="text-xl font-black text-amber-700 mt-0.5 tabular-nums">{{ number_format($loanNeedsReview) }}</p>
                        </div>
                        <div class="rounded-xl bg-sky-50 border border-sky-100 px-3.5 py-3">
                            <p class="text-xs text-sky-700 font-medium">Processing</p>
                            <p class="text-xl font-black text-sky-700 mt-0.5 tabular-nums">{{ number_format($loanProcessing) }}</p>
                        </div>
                        <div class="rounded-xl bg-indigo-50 border border-indigo-100 px-3.5 py-3">
                            <p class="text-xs text-indigo-700 font-medium">Assessed</p>
                            <p class="text-xl font-black text-indigo-700 mt-0.5 tabular-nums">{{ number_format((int) ($loanStatusCounts['assessed'] ?? 0)) }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2.5">
                        <div class="rounded-xl bg-emerald-50 border border-emerald-100 px-3 py-2.5">
                            <p class="text-xs font-medium text-emerald-700">Eligible</p>
                            <p class="text-lg font-black text-emerald-700 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Indicatively Eligible'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-rose-50 border border-rose-100 px-3 py-2.5">
                            <p class="text-xs font-medium text-rose-700">Not Eligible</p>
                            <p class="text-lg font-black text-rose-700 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Not Eligible Under Current Rules'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2.5">
                            <p class="text-xs font-medium text-amber-700">Review</p>
                            <p class="text-lg font-black text-amber-700 mt-0.5 tabular-nums">{{ number_format((int) ($loanOutcomeCounts['Needs Review'] ?? 0)) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($showRecruitment)
            <div class="glass-panel rounded-2xl overflow-hidden {{ $showLoans ? '' : 'xl:col-span-3' }}">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <h3 class="text-sm font-bold text-slate-900">Recent AI Interviews</h3>
                    <a href="{{ route('recruitment.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Manage</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentInterviews as $interview)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/80 transition-colors">
                            <div class="min-w-0 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($interview->candidate_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $interview->candidate_name }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 truncate">{{ $interview->applied_role }} · {{ ucfirst($interview->status) }}</p>
                                </div>
                            </div>
                            @if($interview->status === 'completed')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100 whitespace-nowrap">Completed</span>
                            @else
                                <a href="{{ route('recruitment.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 whitespace-nowrap">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-slate-500">No recruitment activity yet.</div>
                    @endforelse
                </div>
            </div>
        @endif

        @if($showLoans)
            <div class="glass-panel rounded-2xl overflow-hidden {{ $showRecruitment ? '' : ($mode === 'analyst' ? 'xl:col-span-3' : 'xl:col-span-3') }}">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <h3 class="text-sm font-bold text-slate-900">
                        {{ $mode === 'analyst' ? 'Recent Applications' : 'Recent Loan Activity' }}
                    </h3>
                    <a href="{{ route('loan-applications.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Manage</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentLoans as $loan)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/80 transition-colors">
                            <div class="min-w-0 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-700 text-sm font-bold shrink-0">
                                    {{ strtoupper(substr($loan->applicant->name ?? '?', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
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
                                <a href="{{ route('loan-applications.report', $loan->id) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 whitespace-nowrap">Report</a>
                            @else
                                <a href="{{ route('loan-applications.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 whitespace-nowrap">Open</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-slate-500">No loan activity yet.</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
    @endif

    <script>
        (function () {
            const el = document.getElementById('welcome-type-text');
            const caret = document.getElementById('welcome-type-caret');
            if (!el) return;

            const full = el.getAttribute('data-welcome') || el.textContent || '';
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduceMotion) {
                el.textContent = full;
                if (caret) caret.style.display = 'none';
                return;
            }

            const typeMs = 70;
            const deleteMs = 45;
            const holdFullMs = 2800;
            const holdEmptyMs = 700;
            let i = 0;
            let deleting = false;

            el.textContent = '';

            function tick() {
                if (!deleting) {
                    i += 1;
                    el.textContent = full.slice(0, i);
                    if (i >= full.length) {
                        deleting = true;
                        setTimeout(tick, holdFullMs);
                        return;
                    }
                    setTimeout(tick, typeMs);
                    return;
                }

                i -= 1;
                el.textContent = full.slice(0, Math.max(0, i));
                if (i <= 0) {
                    deleting = false;
                    setTimeout(tick, holdEmptyMs);
                    return;
                }
                setTimeout(tick, deleteMs);
            }

            setTimeout(tick, 400);
        })();
    </script>
</x-dark-layout>
