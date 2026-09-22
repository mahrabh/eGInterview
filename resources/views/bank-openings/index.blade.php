<x-dark-layout>
    <div class="flex flex-col gap-6 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Bank Account Opening</h2>
                <p class="mt-1.5 max-w-xl text-xs sm:text-[13px] leading-relaxed text-slate-500 font-medium">Generate a blank public link, track applicant progress, and review submissions.</p>
            </div>

            <form action="{{ route('bank-openings.store') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl font-semibold text-sm text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Generate Link
                </button>
            </form>
        </div>

        <form action="{{ route('bank-openings.index') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 flex-wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, ref, phone..." class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-800 bg-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-colors w-full sm:w-56">

            @if(auth()->user()->isAdmin() && isset($users))
            <select name="user_id" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 bg-white outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                <option value="">All Created Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            @endif

            <select name="stage" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 bg-white outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                <option value="">All Stages</option>
                @foreach($stages as $stage)
                    <option value="{{ $stage->value }}" {{ request('stage') === $stage->value ? 'selected' : '' }}>{{ $stage->label() }}</option>
                @endforeach
            </select>

            <div class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $applications->count() }} on this page</span>
            </div>

            <button type="submit" class="hidden"></button>
        </form>
    </div>

    @if(session('success') && ! session('generated_link_url'))
        <div class="flash-message mb-6 bg-emerald-50 text-emerald-900 px-5 py-3.5 rounded-xl border border-emerald-200 text-sm font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('generated_link_url'))
        <div class="flash-message mb-6 bg-indigo-50 text-indigo-950 px-5 py-4 rounded-xl border border-indigo-200 shadow-sm">
            <p class="text-sm font-semibold">{{ session('success') ?: 'Public link ready — share it with the applicant.' }}</p>
            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                <input type="text" readonly value="{{ session('generated_link_url') }}" class="flex-1 px-3 py-2 rounded-lg border border-indigo-200 bg-white text-xs font-mono text-slate-800">
                <button type="button" onclick="navigator.clipboard.writeText(@js(session('generated_link_url')))" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 shrink-0">Copy link</button>
            </div>
            <p class="mt-2 text-[11px] text-indigo-700/80">This notice closes in 5 seconds.</p>
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 font-bold">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Account Type</th>
                        <th class="px-4 py-3">Stage</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $application)
                        @php
                            $applicant = $application->applicant;
                            $linkActive = $application->hasActivePublicLink();
                            $publicUrl = $linkActive ? $application->publicUrl() : null;
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $applicant->displayName() }}</p>
                                <p class="mt-0.5 font-mono text-xs font-semibold text-slate-500">{{ $applicant->application_reference }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $applicant->phone_masked ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $application->accountTypeLabel() ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold">{{ $application->stage->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $application->created_at?->format('M j, Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('bank-openings.show', $application) }}" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50">Details</a>

                                    <form action="{{ route('bank-openings.generate-link', $application) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-indigo-200 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                            {{ $linkActive ? 'Regenerate' : 'Generate Link' }}
                                        </button>
                                    </form>

                                    @if($linkActive && $publicUrl)
                                        <button type="button" onclick="navigator.clipboard.writeText(@js($publicUrl))" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50" title="Copy link">Copy</button>
                                    @endif

                                    <form action="{{ route('bank-openings.destroy', $application) }}" method="POST" class="inline" onsubmit="return confirm('Delete this application?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-rose-200 text-xs font-semibold text-rose-700 hover:bg-rose-50">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500 text-sm">
                                No bank account opening applications yet. Click <strong>Generate Blank Link</strong> to invite an applicant.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($applications->hasMorePages() || $applications->currentPage() > 1)
            <div class="px-4 py-3 border-t border-slate-200">
                {{ $applications->links() }}
            </div>
        @endif
    </div>

    @php
        $terminalStages = [
            \App\Enums\BankOpeningStage::Submitted,
            \App\Enums\BankOpeningStage::UnderReview,
            \App\Enums\BankOpeningStage::Completed,
        ];

        $needsStatusPolling = function ($item) use ($terminalStages) {
            return ! in_array($item->stage, $terminalStages, true);
        };

        $pollingApplications = $applications->filter($needsStatusPolling)->values();
        $pollingApplicationIds = $pollingApplications->pluck('id')->values();
        $initialStatusSnapshot = $pollingApplications->mapWithKeys(function ($item) {
            return [
                $item->id => [
                    'stage' => $item->stage instanceof \App\Enums\BankOpeningStage
                        ? $item->stage->value
                        : (string) $item->stage,
                    'account_type' => $item->account_type,
                    'documents_label' => $item->documentProgressLabel(),
                    'interview_completed_at' => $item->interview_completed_at?->toIso8601String(),
                    'submitted_at' => $item->submitted_at?->toIso8601String(),
                    'documents_submitted_at' => $item->documents_submitted_at?->toIso8601String(),
                    'updated_at' => $item->updated_at?->toIso8601String(),
                ],
            ];
        });
    @endphp

    @if($pollingApplicationIds->isNotEmpty())
    <script>
    (function () {
        const applicationIds = @json($pollingApplicationIds);
        const initialSnapshot = @json($initialStatusSnapshot);
        const snapshotUrl = @json(route('bank-openings.status-snapshot'));
        let attempts = 0;
        const maxAttempts = 90;
        const pollEveryMs = 5000;

        const hasSnapshotChanged = function (current) {
            for (const applicationId of applicationIds) {
                const initial = initialSnapshot[applicationId];
                const latest = current[applicationId];

                if (!initial || !latest) {
                    continue;
                }

                if (
                    initial.stage !== latest.stage
                    || initial.account_type !== latest.account_type
                    || initial.documents_label !== latest.documents_label
                    || initial.interview_completed_at !== latest.interview_completed_at
                    || initial.submitted_at !== latest.submitted_at
                    || initial.documents_submitted_at !== latest.documents_submitted_at
                    || initial.updated_at !== latest.updated_at
                ) {
                    return true;
                }
            }

            return false;
        };

        const poll = async function () {
            if (document.hidden || attempts >= maxAttempts) {
                return;
            }

            attempts++;

            try {
                const response = await fetch(`${snapshotUrl}?ids=${encodeURIComponent(applicationIds.join(','))}`, {
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

                if (hasSnapshotChanged(data.applications || {})) {
                    window.location.reload();
                }
            } catch (error) {
                // Ignore transient network errors and keep polling.
            }
        };

        // Delay first poll so the list paints before background traffic starts.
        let intervalId = null;
        const startId = window.setTimeout(function () {
            poll();
            intervalId = window.setInterval(poll, pollEveryMs);
        }, 4000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && attempts < maxAttempts) {
                poll();
            }
        });

        window.addEventListener('beforeunload', function () {
            window.clearTimeout(startId);
            if (intervalId) {
                window.clearInterval(intervalId);
            }
        });
    })();
    </script>
    @endif
</x-dark-layout>
