<x-dark-layout>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div>
            <h2 class="text-4xl font-black text-white tracking-tight">Dashboard</h2>
            <p class="text-slate-400 mt-2 text-sm font-medium">System overview across recruitment and loan interviews.</p>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="glass-panel rounded-[1.5rem] border border-slate-800 p-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Recruitment Candidates</p>
            <p class="text-3xl font-black text-white mt-3">{{ number_format($totalCandidates) }}</p>
            <p class="text-xs text-slate-500 mt-2">Total candidates</p>
        </div>

        <div class="glass-panel rounded-[1.5rem] border border-slate-800 p-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Loan Applicants</p>
            <p class="text-3xl font-black text-white mt-3">{{ number_format($totalLoanApplicants) }}</p>
            <p class="text-xs text-slate-500 mt-2">Total loan applicants</p>
        </div>

        <div class="glass-panel rounded-[1.5rem] border border-slate-800 p-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Completed Interviews</p>
            <p class="text-3xl font-black text-white mt-3">{{ number_format($completedInterviews) }}</p>
            <p class="text-xs text-slate-500 mt-2">Recruitment completed</p>
        </div>

        <div class="glass-panel rounded-[1.5rem] border border-slate-800 p-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Needs Attention</p>
            <p class="text-3xl font-black text-white mt-3">{{ number_format($needsAttention) }}</p>
            <p class="text-xs text-slate-500 mt-2">Pending, expired, review, processing</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Recruitment status --}}
        <div class="glass-panel rounded-[2rem] border border-slate-800 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-300">Recruitment Status</h3>
                <a href="{{ route('recruitment.index') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">View all</a>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Draft</p>
                    <p class="text-2xl font-black text-white mt-1">{{ number_format($draftCandidates) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Pending Approval</p>
                    <p class="text-2xl font-black text-amber-300 mt-1">{{ number_format($pendingApproval) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Active Links</p>
                    <p class="text-2xl font-black text-indigo-300 mt-1">{{ number_format($activeLinks) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Expired Links</p>
                    <p class="text-2xl font-black text-rose-300 mt-1">{{ number_format($expiredLinks) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4 col-span-2">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Completed</p>
                    <p class="text-2xl font-black text-emerald-300 mt-1">{{ number_format($completedInterviews) }}</p>
                </div>
            </div>
        </div>

        {{-- Loan status / outcome --}}
        <div class="glass-panel rounded-[2rem] border border-slate-800 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-300">Loan Applications</h3>
                <a href="{{ route('loan-applications.index') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">View all</a>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Draft</p>
                    <p class="text-2xl font-black text-white mt-1">{{ number_format((int) ($loanStatusCounts['draft'] ?? 0)) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Needs Review</p>
                    <p class="text-2xl font-black text-amber-300 mt-1">{{ number_format($loanNeedsReview) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Processing</p>
                    <p class="text-2xl font-black text-blue-300 mt-1">{{ number_format($loanProcessing) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900/70 border border-slate-800 px-4 py-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Assessed</p>
                    <p class="text-2xl font-black text-indigo-300 mt-1">{{ number_format((int) ($loanStatusCounts['assessed'] ?? 0)) }}</p>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-emerald-500/5 border border-emerald-500/20 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/80">Eligible</p>
                    <p class="text-lg font-black text-emerald-300 mt-1">{{ number_format((int) ($loanOutcomeCounts['Indicatively Eligible'] ?? 0)) }}</p>
                </div>
                <div class="rounded-2xl bg-rose-500/5 border border-rose-500/20 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-400/80">Not Eligible</p>
                    <p class="text-lg font-black text-rose-300 mt-1">{{ number_format((int) ($loanOutcomeCounts['Not Eligible Under Current Rules'] ?? 0)) }}</p>
                </div>
                <div class="rounded-2xl bg-amber-500/5 border border-amber-500/20 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-400/80">Review</p>
                    <p class="text-lg font-black text-amber-300 mt-1">{{ number_format((int) ($loanOutcomeCounts['Needs Review'] ?? 0)) }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent recruitment --}}
        <div class="glass-panel rounded-[2rem] border border-slate-800 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800/60 flex items-center justify-between">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-300">Recent Recruitment</h3>
                <a href="{{ route('recruitment.index') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">Manage</a>
            </div>
            <div class="divide-y divide-slate-800/50">
                @forelse($recentInterviews as $interview)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-bold text-white truncate">{{ $interview->candidate_name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate">{{ $interview->applied_role }} · {{ ucfirst($interview->status) }}</p>
                        </div>
                        @if($interview->status === 'completed')
                            <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-400/80 whitespace-nowrap">Completed</span>
                        @else
                            <a href="{{ route('recruitment.index') }}" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-indigo-300 whitespace-nowrap">Open</a>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">No recruitment activity yet.</div>
                @endforelse
            </div>
        </div>

        {{-- Recent loans --}}
        <div class="glass-panel rounded-[2rem] border border-slate-800 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-800/60 flex items-center justify-between">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-300">Recent Loan Activity</h3>
                <a href="{{ route('loan-applications.index') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">Manage</a>
            </div>
            <div class="divide-y divide-slate-800/50">
                @forelse($recentLoans as $loan)
                    <div class="px-6 py-4 flex items-center justify-between gap-4 hover:bg-slate-900/40 transition-colors">
                        <div class="min-w-0">
                            <p class="font-bold text-white truncate">{{ $loan->applicant->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                {{ $loan->applicant->application_reference ?? '—' }} · {{ ucfirst(str_replace('_', ' ', $loan->status ?? 'draft')) }}
                                @if($loan->outcome)
                                    · {{ $loan->outcome }}
                                @endif
                            </p>
                        </div>
                        @php
                            $canOpenReport = $loan->submitted_at !== null
                                || in_array($loan->status, ['pending', 'needs_review', 'assessed', 'processing'], true)
                                || is_array($loan->extracted_data);
                        @endphp
                        @if($canOpenReport)
                            <a href="{{ route('loan-applications.report', $loan->id) }}" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-indigo-300 whitespace-nowrap">Report</a>
                        @else
                            <a href="{{ route('loan-applications.index') }}" class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-indigo-300 whitespace-nowrap">Open</a>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500">No loan activity yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-dark-layout>
