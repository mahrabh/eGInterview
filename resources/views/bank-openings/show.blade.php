<x-dark-layout>
    <div class="mb-5">
        <a href="{{ route('bank-openings.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">← Back to Bank Account Opening</a>
    </div>

    @if(session('success') && ! session('generated_link_url'))
        <div class="flash-message mb-5 bg-emerald-50 text-emerald-900 px-5 py-3 rounded-xl border border-emerald-200 text-sm font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if(session('generated_link_url'))
        <div id="bao-link-toast" class="flash-message mb-5 bg-indigo-50 text-indigo-950 px-5 py-4 rounded-xl border border-indigo-200 shadow-sm">
            <p class="text-sm font-semibold">{{ session('success') ?: 'Public link ready — share it with the applicant.' }}</p>
            <div class="mt-3 flex flex-col sm:flex-row gap-2">
                <input type="text" readonly value="{{ session('generated_link_url') }}" class="flex-1 px-3 py-2 rounded-lg border border-indigo-200 bg-white text-xs font-mono text-slate-800">
                <button type="button" onclick="navigator.clipboard.writeText(@js(session('generated_link_url')))" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 shrink-0">Copy link</button>
            </div>
            <p class="mt-2 text-[11px] text-indigo-700/80">This notice closes in 5 seconds. The link stays available below while it is active.</p>
        </div>
    @endif

    @php
        $meta = is_array($application->meta) ? $application->meta : [];
        $pendingResubmission = is_array($meta['resubmission'] ?? null) ? $meta['resubmission'] : null;
        $lastResubmission = is_array($meta['last_resubmission'] ?? null) ? $meta['last_resubmission'] : null;
        // Officer view always lists full requirement set (not filtered to pending reopen groups).
        $officerMeta = $meta;
        unset($officerMeta['resubmission']);
        $docGroups = \App\Support\BankOpening\DocumentRequirementCatalog::groupsForAccountType(
            $application->account_type,
            $officerMeta
        );
        $groupLabelMap = collect($docGroups)->mapWithKeys(fn ($g) => [$g['key'] => $g['label']]);
        $activeDocs = $application->documents->whereNull('removed_at');
        $docsByType = $activeDocs->groupBy('document_type');
        $answers = $application->structured_answers ?? [];
        $summary = $application->confirmed_summary ?? [];
        $transcriptBody = $application->transcript_text ?: $application->transcript;
        // Live application fields are source of truth — never prefer stale summary snapshot.
        $displayAccountType = $application->accountTypeLabel()
            ?: ($summary['account_type_name'] ?? null);
        $summaryFields = (! empty($summary['fields']) && is_array($summary['fields']))
            ? $summary['fields']
            : [];
        $shouldPollStatus = ! in_array($application->stage, [
            \App\Enums\BankOpeningStage::Submitted,
            \App\Enums\BankOpeningStage::UnderReview,
            \App\Enums\BankOpeningStage::Completed,
        ], true);
    @endphp

    @if($application->stage === \App\Enums\BankOpeningStage::ResubmissionRequired && $pendingResubmission)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Resubmission pending</p>
            <p class="mt-1 text-sm font-semibold text-amber-950">Waiting for the applicant to upload and submit the requested documents.</p>
            @if(! empty($pendingResubmission['reason']))
                <p class="mt-2 text-xs text-amber-900/80"><span class="font-semibold">Reason:</span> {{ $pendingResubmission['reason'] }}</p>
            @endif
            @if(! empty($pendingResubmission['groups']) && is_array($pendingResubmission['groups']))
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($pendingResubmission['groups'] as $groupKey)
                        <li class="inline-flex px-2 py-0.5 rounded-md bg-white border border-amber-200 text-[11px] font-semibold text-amber-900">
                            {{ $groupLabelMap[$groupKey] ?? str_replace('_', ' ', (string) $groupKey) }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @if(! empty($pendingResubmission['requested_at']))
                <p class="mt-2 text-[11px] text-amber-800/70">Requested {{ \Illuminate\Support\Carbon::parse($pendingResubmission['requested_at'])->format('M j, Y g:i A') }}</p>
            @endif
        </div>
    @elseif($lastResubmission && ($lastResubmission['status'] ?? null) === 'fulfilled')
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Resubmission completed</p>
            <p class="mt-1 text-sm font-semibold text-emerald-950">Applicant submitted the requested documents for review.</p>
            @if(! empty($lastResubmission['groups']) && is_array($lastResubmission['groups']))
                <ul class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($lastResubmission['groups'] as $groupKey)
                        <li class="inline-flex px-2 py-0.5 rounded-md bg-white border border-emerald-200 text-[11px] font-semibold text-emerald-900">
                            {{ $groupLabelMap[$groupKey] ?? str_replace('_', ' ', (string) $groupKey) }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @if(! empty($lastResubmission['completed_at']))
                <p class="mt-2 text-[11px] text-emerald-800/70">Completed {{ \Illuminate\Support\Carbon::parse($lastResubmission['completed_at'])->format('M j, Y g:i A') }}</p>
            @endif
        </div>
    @endif

    <div class="space-y-5">
        {{-- Top: overview + summary | activity --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
            <div class="xl:col-span-8 space-y-5 min-w-0">
                <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-100">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Application reference</p>
                            <h1 class="mt-0.5 text-xl sm:text-2xl font-bold text-slate-900 font-mono tracking-tight truncate">
                                {{ $application->applicant->application_reference }}
                            </h1>
                            <p class="mt-1 text-xs text-slate-500">Created {{ $application->created_at?->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6">
                        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Applicant name</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 leading-snug">{{ $application->applicant->displayName() }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Phone</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $application->applicant->phone_masked ?: '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">UCB account type</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 leading-snug">{{ $displayAccountType ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Current stage</dt>
                                <dd class="mt-1.5">
                                    <span class="inline-flex px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[11px] font-bold">{{ $application->stage->label() }}</span>
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Document progress</dt>
                                <dd class="mt-2 flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                        <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $application->documentProgressPercent() }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-700 tabular-nums">{{ $application->documentProgressLabel() }}</span>
                                </dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Documents submitted</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $application->documents_submitted_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Created by</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $application->applicant->creator?->name ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Link expiry</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">
                                    @if($application->public_token_expiry)
                                        {{ $application->public_token_expiry->format('M j, Y g:i A') }}
                                        @if($application->public_token_expiry->isPast())
                                            <span class="text-rose-600 text-xs">(expired)</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @php
                            $activePublicUrl = session('generated_link_url') ?: $application->publicUrl();
                            $needsApplicantLink = $application->stage === \App\Enums\BankOpeningStage::ResubmissionRequired
                                || (! $application->isApplicationSubmitted() && $application->public_token_expiry?->isFuture());
                        @endphp

                        @if($needsApplicantLink)
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                @if($application->stage === \App\Enums\BankOpeningStage::ResubmissionRequired)
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-600 mb-2">Applicant resubmission link</p>
                                    <p class="text-xs text-slate-500 mb-2">Share this link so the applicant can upload the requested documents.</p>
                                @else
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Active public link</p>
                                @endif

                                @if($activePublicUrl)
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="text" readonly value="{{ $activePublicUrl }}" class="flex-1 px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-mono text-slate-800">
                                        <button type="button" onclick="navigator.clipboard.writeText(@js($activePublicUrl))" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">Copy</button>
                                    </div>
                                    @if($application->public_token_expiry)
                                        <p class="mt-1.5 text-[11px] text-slate-400">Expires {{ $application->public_token_expiry->format('M j, Y g:i A') }}</p>
                                    @endif
                                @else
                                    <p class="text-xs text-amber-700 mb-2">No shareable link is available. Use <span class="font-semibold">Regenerate</span> on the Bank Account Opening list to create one.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3">Interview summary</h2>
                        @if($displayAccountType)
                            <p class="text-sm text-slate-800 mb-3">
                                <span class="font-semibold text-slate-500">Account</span>
                                <span class="ml-2 font-semibold">{{ $displayAccountType }}</span>
                            </p>
                        @endif
                        @if(! empty($summaryFields))
                            <ul class="space-y-2">
                                @foreach($summaryFields as $field)
                                    <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                        <span class="font-semibold text-slate-700">{{ $field['label'] ?? $field['key'] ?? 'Field' }}:</span>
                                        <span class="text-slate-600">{{ $field['value'] ?? '—' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif(! empty($answers))
                            <ul class="space-y-2">
                                @foreach($answers as $key => $payload)
                                    <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                        <span class="font-semibold text-slate-700">{{ str_replace('_', ' ', ucwords((string) $key, '_')) }}:</span>
                                        <span class="text-slate-600">
                                            {{ is_array($payload) ? ($payload['display'] ?? $payload['text'] ?? json_encode($payload)) : $payload }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">No structured answers stored for this session.</p>
                        @endif
                    </section>

                    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col">
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3">Live interview transcript</h2>
                        @if($transcriptBody)
                            <p class="text-sm text-slate-500 mb-4 flex-1">Full spoken interview record is available for review.</p>
                            <button
                                type="button"
                                id="bao-view-transcript"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800"
                            >
                                View full transcript
                            </button>
                            <script type="application/json" id="bao-transcript-json">@json($transcriptBody)</script>
                            <script type="application/json" id="bao-transcript-name">@json($application->applicant->displayName())</script>
                        @else
                            <p class="text-sm text-slate-500">No live transcript saved yet.</p>
                        @endif
                    </section>
                </div>
            </div>

            <aside class="xl:col-span-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] flex flex-col overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 shrink-0">
                        <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Activity</h2>
                        <p class="mt-1 text-xs text-slate-400">{{ $application->events->count() }} event(s)</p>
                    </div>
                    <ul class="p-4 space-y-2 overflow-y-auto flex-1 min-h-[320px]">
                        @forelse($application->events as $event)
                            <li class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-800 capitalize">{{ str_replace('_', ' ', $event->event_type) }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $event->created_at?->format('M j, Y g:i A') }}</p>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500 py-6 text-center">No events yet.</li>
                        @endforelse
                    </ul>
                </div>
            </aside>
        </div>

        {{-- Full-width work area: documents + officer actions (fills former empty right column) --}}
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-3">
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Documents by requirement</h2>
                <p class="text-[11px] text-slate-400 tabular-nums">{{ $activeDocs->count() }} file(s) total</p>
            </div>

            @if(empty($docGroups))
                <p class="px-5 py-6 text-sm text-slate-500">No document groups for this account type yet.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($docGroups as $group)
                        @php $files = $docsByType->get($group['key'], collect()); @endphp
                        <li class="px-5 py-3 flex flex-col md:flex-row md:items-center gap-2 md:gap-8">
                            <div class="md:w-72 lg:w-80 shrink-0 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 leading-snug">{{ $group['label'] }}</p>
                                <div class="mt-0.5 flex items-center gap-2">
                                    @if($group['required'])
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-amber-600">Required</span>
                                    @endif
                                    <span class="text-[11px] text-slate-400 tabular-nums">{{ $files->count() }} file(s)</span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($files->isEmpty())
                                    <p class="text-xs text-slate-400">No files uploaded.</p>
                                @else
                                    <ul class="space-y-1.5">
                                        @foreach($files as $doc)
                                            <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-xs font-semibold text-slate-800 truncate">{{ $doc->original_name }}</p>
                                                    <p class="text-[10px] text-slate-500">{{ $doc->created_at?->format('M j, Y g:i A') }}</p>
                                                </div>
                                                <a href="{{ route('bank-openings.documents.download', [$application, $doc]) }}" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-500 shrink-0">Download</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="px-5 py-3 border-t border-slate-100 text-[11px] text-slate-400">Manual review only — do not mark approved from document verification alone.</p>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3">Officer notes</h2>
                <form action="{{ route('bank-openings.notes', $application) }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="notes" rows="6" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="Internal notes for this application…">{{ old('notes', $application->notes) }}</textarea>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">Save notes</button>
                </form>
            </section>

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <h2 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3">Request document resubmission</h2>
                <form action="{{ route('bank-openings.request-resubmission', $application) }}" method="POST" class="space-y-3">
                    @csrf
                    <textarea name="reason" rows="3" required class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20" placeholder="Reason shown to the applicant…"></textarea>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                        @foreach($docGroups as $group)
                            <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs">
                                <input type="checkbox" name="groups[]" value="{{ $group['key'] }}" class="mt-0.5 rounded border-slate-300">
                                <span class="font-semibold text-slate-800 leading-snug">{{ $group['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500">Request resubmission</button>
                </form>
            </section>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const button = document.getElementById('bao-view-transcript');
            if (button) {
                button.addEventListener('click', function () {
                    const nameEl = document.getElementById('bao-transcript-name');
                    const textEl = document.getElementById('bao-transcript-json');
                    let name = 'Applicant';
                    let text = '';
                    try { name = JSON.parse(nameEl?.textContent || '"Applicant"'); } catch (e) {}
                    try { text = JSON.parse(textEl?.textContent || '""'); } catch (e) {}
                    showTranscript(name, text);
                });
            }
        })();

        @if($shouldPollStatus)
        (function () {
            const applicationId = @json($application->id);
            const snapshotUrl = @json(route('bank-openings.status-snapshot'));
            const initial = {
                stage: @json($application->stage instanceof \App\Enums\BankOpeningStage ? $application->stage->value : (string) $application->stage),
                account_type: @json($application->account_type),
                documents_label: @json($application->documentProgressLabel()),
                interview_completed_at: @json($application->interview_completed_at?->toIso8601String()),
                submitted_at: @json($application->submitted_at?->toIso8601String()),
                documents_submitted_at: @json($application->documents_submitted_at?->toIso8601String()),
                updated_at: @json($application->updated_at?->toIso8601String()),
            };
            let attempts = 0;
            const maxAttempts = 90;
            const pollEveryMs = 5000;
            let intervalId = null;

            const changed = function (latest) {
                if (!latest) return false;
                return initial.stage !== latest.stage
                    || initial.account_type !== latest.account_type
                    || initial.documents_label !== latest.documents_label
                    || initial.interview_completed_at !== latest.interview_completed_at
                    || initial.submitted_at !== latest.submitted_at
                    || initial.documents_submitted_at !== latest.documents_submitted_at
                    || initial.updated_at !== latest.updated_at;
            };

            const poll = async function () {
                if (document.hidden || attempts >= maxAttempts) return;
                attempts++;
                try {
                    const response = await fetch(`${snapshotUrl}?ids=${encodeURIComponent(applicationId)}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (changed((data.applications || {})[applicationId])) {
                        window.location.reload();
                    }
                } catch (e) {}
            };

            const startId = window.setTimeout(function () {
                poll();
                intervalId = window.setInterval(poll, pollEveryMs);
            }, 2500);

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && attempts < maxAttempts) poll();
            });
            window.addEventListener('beforeunload', function () {
                window.clearTimeout(startId);
                if (intervalId) window.clearInterval(intervalId);
            });
        })();
        @endif
    </script>
    @endpush
</x-dark-layout>
