<x-dark-layout>
    <div class="flex flex-col gap-6 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Loan Applicants</h2>
            <p class="text-slate-500 mt-1.5 text-sm">Download the template, replace example rows with real applicants, then import.</p>
        </div>

        <div class="flex flex-col xl:flex-row xl:items-center gap-3 flex-wrap">
            <form action="{{ route('loan-applications.index') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 flex-1 min-w-0">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, ref, phone..." class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-800 bg-white outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-colors w-full sm:w-56">

                @if(auth()->user()->isAdmin() && isset($users))
                <select name="user_id" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 bg-white outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                    <option value="">All Created Users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
                @endif

                <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 bg-white outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="needs_review" {{ request('status') === 'needs_review' ? 'selected' : '' }}>Needs Review</option>
                    <option value="assessed" {{ request('status') === 'assessed' ? 'selected' : '' }}>Assessed</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>

                <button type="submit" class="hidden"></button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <div class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $applications->total() }} Records</span>
                </div>

                <a href="{{ route('loan-applications.download-template') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl font-semibold text-xs text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Template
                </a>

                <form action="{{ route('loan-applications.import') }}" method="POST" enctype="multipart/form-data" class="inline-flex">
                    @csrf
                    <label class="cursor-pointer border border-indigo-200 hover:border-indigo-300 bg-indigo-50 hover:bg-indigo-100 px-4 py-2.5 rounded-xl flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span class="text-sm font-semibold text-indigo-700">Import XLSX/CSV</span>
                        <input type="file" name="csv_file" accept=".csv,.xlsx" required class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div id="flash-success" class="flash-message bg-emerald-50 text-emerald-700 px-5 py-3.5 rounded-xl border border-emerald-200 font-semibold text-sm flex items-center gap-3 mb-6">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div id="flash-error" class="flash-message bg-rose-50 text-rose-700 px-5 py-3.5 rounded-xl border border-rose-200 font-semibold text-sm flex items-center gap-3 mb-6">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div id="flash-error" class="flash-message bg-rose-50 text-rose-700 px-5 py-3.5 rounded-xl border border-rose-200 font-semibold text-sm flex items-center gap-3 mb-6">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ $errors->first() }}
    </div>
    @endif

    <div class="glass-panel rounded-2xl overflow-hidden relative">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[720px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-5 sm:px-6 py-3.5 text-left text-xs font-bold tracking-wide text-slate-500 uppercase">Applicant</th>
                        <th class="px-5 sm:px-6 py-3.5 text-left text-xs font-bold tracking-wide text-slate-500 uppercase">Phone</th>
                        <th class="px-5 sm:px-6 py-3.5 text-left text-xs font-bold tracking-wide text-slate-500 uppercase">Status</th>
                        <th class="px-5 sm:px-6 py-3.5 text-right text-xs font-bold tracking-wide text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $item)
                    @php
                        $applicant = $item->applicant;
                        $statusLabel = match ($item->outcome) {
                            'Indicatively Eligible' => 'Approved',
                            'Not Eligible Under Current Rules' => 'Rejected',
                            'Needs Review' => 'Needs Review',
                            default => ucfirst(str_replace('_', ' ', $item->status ?? 'draft')),
                        };
                        $statusTone = match ($item->outcome) {
                            'Indicatively Eligible' => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                            'Not Eligible Under Current Rules' => 'bg-rose-50 border-rose-200 text-rose-700',
                            'Needs Review' => 'bg-amber-50 border-amber-200 text-amber-700',
                            default => match ($item->status) {
                                'assessed' => 'bg-indigo-50 border-indigo-200 text-indigo-700',
                                'processing' => 'bg-sky-50 border-sky-200 text-sky-700',
                                'needs_review' => 'bg-amber-50 border-amber-200 text-amber-700',
                                default => 'bg-slate-100 border-slate-200 text-slate-600',
                            },
                        };
                        $canViewReport = $item->submitted_at !== null
                            || in_array($item->status, ['pending', 'needs_review', 'assessed', 'processing'], true)
                            || is_array($item->extracted_data);
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center gap-3 sm:gap-4">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($applicant->name ?? 'A', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-slate-900 text-sm truncate">
                                        @if($canViewReport)
                                            <a href="{{ route('loan-applications.report', $item->id) }}" class="hover:text-indigo-600 transition-colors">{{ $applicant->name ?? 'Unknown' }}</a>
                                        @else
                                            {{ $applicant->name ?? 'Unknown' }}
                                        @endif
                                    </h3>
                                    @if($applicant->application_reference ?? false)
                                        <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $applicant->application_reference }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        <td class="px-5 sm:px-6 py-4 text-sm text-slate-600">
                            {{ $applicant->masked_phone ?? 'N/A' }}
                        </td>

                        <td class="px-5 sm:px-6 py-4">
                            <span class="inline-flex px-3 py-1 text-xs font-semibold border rounded-full {{ $statusTone }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                @php
                                    $isDraft = $item->status === 'draft';
                                    $isLinkActive = $isDraft && $item->public_token_hash && $item->public_token_expiry && $item->public_token_expiry->isFuture();
                                    $activeUrl = '';
                                    if ($isLinkActive) {
                                        $token = hash_hmac('sha256', $item->id . $item->public_token_expiry->timestamp, config('app.key'));
                                        $activeUrl = url("/loan-interview/{$token}");
                                    }
                                @endphp

                                @if($isLinkActive)
                                    <span class="flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-xs font-semibold whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Link Active
                                    </span>
                                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl p-1 pr-2">
                                        <input type="text" readonly value="{{ $activeUrl }}" class="text-xs bg-transparent w-36 sm:w-48 text-slate-600 outline-none px-2 font-mono" onclick="this.select();">
                                        <a href="{{ $activeUrl }}" target="_blank" class="w-8 h-8 flex items-center justify-center bg-white hover:bg-indigo-600 hover:text-white text-slate-600 rounded-lg border border-slate-200 transition-colors" title="Open Link">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </div>
                                @endif

                                <div class="flex items-center gap-1">
                                    @if($isDraft)
                                        @if(!$isLinkActive)
                                            <form action="{{ route('loan-applications.generate-link', $item->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-9 h-9 flex items-center justify-center text-indigo-600 hover:bg-indigo-50 rounded-xl border border-transparent hover:border-indigo-200 transition-all" title="Generate Link">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('loan-applications.generate-link', $item->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-xl border border-transparent hover:border-amber-200 transition-all" title="Regenerate Link">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    @if($canViewReport)
                                        <a href="{{ route('loan-applications.report', $item->id) }}" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-xl border border-transparent hover:border-emerald-200 transition-all" title="View Full Report">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        </a>
                                    @endif
                                </div>

                                <div class="w-px h-6 bg-slate-200 mx-0.5 hidden sm:block"></div>

                                <form action="{{ route('loan-applications.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this application permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-xl border border-transparent hover:border-rose-200 transition-all" title="Delete Application">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center border border-slate-200">
                                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                </div>
                                <div>
                                    <h4 class="text-slate-800 font-bold mb-1">No applicants found</h4>
                                    <p class="text-slate-500 text-sm">Import a CSV file to begin your loan applicant flow.</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex justify-center">
        {{ $applications->links() }}
    </div>

    @php
        $needsStatusPolling = function ($item) {
            if ($item->status === 'processing') {
                return true;
            }

            if ($item->submitted_at !== null && $item->outcome === null) {
                return true;
            }

            if (
                $item->status === 'draft'
                && $item->public_token_hash
                && $item->public_token_expiry
                && $item->public_token_expiry->isFuture()
            ) {
                return true;
            }

            return false;
        };

        $pollingApplications = $applications->filter($needsStatusPolling)->values();
        $pollingApplicationIds = $pollingApplications->pluck('id')->values();
        $initialStatusSnapshot = $pollingApplications->mapWithKeys(function ($item) {
            return [
                $item->id => [
                    'status' => $item->status,
                    'outcome' => $item->outcome,
                    'submitted_at' => $item->submitted_at?->toIso8601String(),
                ],
            ];
        });
    @endphp

    @if($pollingApplicationIds->isNotEmpty())
    <script>
    (function () {
        const applicationIds = @json($pollingApplicationIds);
        const initialSnapshot = @json($initialStatusSnapshot);
        const snapshotUrl = @json(route('loan-applications.status-snapshot'));
        let attempts = 0;
        const maxAttempts = 120;

        const hasSnapshotChanged = function (current) {
            for (const applicationId of applicationIds) {
                const initial = initialSnapshot[applicationId];
                const latest = current[applicationId];

                if (!initial || !latest) {
                    continue;
                }

                if (
                    initial.status !== latest.status
                    || initial.outcome !== latest.outcome
                    || initial.submitted_at !== latest.submitted_at
                ) {
                    return true;
                }

                if (latest.is_report_ready) {
                    return true;
                }
            }

            return false;
        };

        const poll = async function () {
            if (attempts >= maxAttempts) {
                return;
            }

            attempts++;

            try {
                const response = await fetch(`${snapshotUrl}?ids=${applicationIds.join(',')}`, {
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

        poll();
        window.setInterval(poll, 3000);
    })();
    </script>
    @endif

</x-dark-layout>
