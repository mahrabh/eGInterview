<x-dark-layout>
    <div class="flex flex-col gap-6 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Recruitment</h2>
            <p class="text-slate-500 mt-1.5 text-sm">Download the template, replace example rows with real candidates, then import.</p>
        </div>

        <div class="flex flex-col xl:flex-row xl:items-center gap-3 flex-wrap">
            <form action="{{ route('recruitment.index') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 flex-1 min-w-0">
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
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Before Interview</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending Approval</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Link Active</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Link Expired</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>

                <button type="submit" class="hidden"></button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                @if(!auth()->user()->isAdmin() && auth()->user()->plan)
                    @php
                        $quota = auth()->user()->planUsageSnapshot();
                    @endphp
                    <div class="px-3.5 py-2 rounded-xl border border-indigo-200 bg-indigo-50 flex items-center gap-2">
                        <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">
                            {{ auth()->user()->plan->name }} this month:
                            {{ $quota['recruitment_used'] }}
                            @if($quota['recruitment_limit'] !== null)
                                / {{ $quota['recruitment_limit'] }}
                            @endif
                            used
                            @if($quota['recruitment_remaining'] !== null)
                                · {{ $quota['recruitment_remaining'] }} left
                            @endif
                        </span>
                    </div>
                @endif

                <div class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">{{ $interviews->total() }} Records</span>
                </div>

                <a href="{{ route('interviews.download-template') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl font-semibold text-xs text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Template
                </a>

                <form action="{{ route('interviews.import') }}" method="POST" enctype="multipart/form-data" class="inline-flex">
                    @csrf
                    <label class="cursor-pointer border border-indigo-200 hover:border-indigo-300 bg-indigo-50 hover:bg-indigo-100 px-4 py-2.5 rounded-xl flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span class="text-sm font-semibold text-indigo-700">Import CSV</span>
                        <input type="file" name="file" accept=".csv,.xlsx" required class="hidden" onchange="this.form.submit()">
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
                        <th class="px-5 sm:px-6 py-3.5 text-left text-xs font-bold tracking-wide text-slate-500 uppercase">Candidate</th>
                        <th class="px-5 sm:px-6 py-3.5 text-left text-xs font-bold tracking-wide text-slate-500 uppercase">Status</th>
                        <th class="px-5 sm:px-6 py-3.5 text-right text-xs font-bold tracking-wide text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($interviews as $item)
                    @php
                        $eval = is_string($item->evaluation_json) ? json_decode($item->evaluation_json, true) : $item->evaluation_json;
                        $score = $eval['score'] ?? null;
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center gap-3 sm:gap-4">
                                @if($item->has_candidate_photo)
                                    <button type="button"
                                            data-action="show-photo"
                                            data-name="{{ $item->candidate_name }}"
                                            data-photo="{{ route('interviews.photo', $item) }}"
                                            class="w-10 h-10 rounded-xl overflow-hidden border border-slate-200 shrink-0 focus:outline-none hover:ring-2 hover:ring-indigo-200 transition">
                                        <img src="{{ route('interviews.photo', $item) }}" alt="{{ $item->candidate_name }}'s photo" class="w-10 h-10 object-cover" loading="lazy">
                                    </button>
                                @else
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm shrink-0">
                                        {{ strtoupper(substr($item->candidate_name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-slate-900 text-sm truncate">{{ $item->candidate_name }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 truncate">{{ $item->applied_role }}</p>
                                    @unless($item->has_candidate_photo)
                                        <p class="text-[11px] text-slate-400 mt-0.5">No photo captured</p>
                                    @endunless
                                </div>
                            </div>
                        </td>

                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($item->status === 'draft')
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold border rounded-full bg-slate-100 border-slate-200 text-slate-600">Awaiting Setup</span>
                                @elseif($item->status === 'pending')
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold border rounded-full bg-amber-50 border-amber-200 text-amber-700">Pending Review</span>
                                @elseif($item->status === 'approved')
                                    @if($item->link_expires_at && \Carbon\Carbon::parse($item->link_expires_at)->isPast())
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold border rounded-full bg-rose-50 border-rose-200 text-rose-700">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            Link Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold border rounded-full bg-indigo-50 border-indigo-200 text-indigo-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                            Link Active
                                        </span>
                                    @endif
                                @elseif($item->status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold border rounded-full bg-emerald-50 border-emerald-200 text-emerald-700">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                        Completed
                                    </span>
                                    @if($score !== null)
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold border rounded-full {{ $score >= 8 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($score >= 5 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200') }}">
                                            Score: {{ $score }}/10
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>

                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                @if($item->status === 'draft')
                                    <form action="{{ route('interviews.generate', array_filter(['interview' => $item->id, 'page' => request('page')])) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl font-semibold text-xs transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            Generate Questions
                                        </button>
                                    </form>

                                @elseif($item->status === 'pending')
                                    <a href="{{ route('interviews.review', array_filter(['interview' => $item->id, 'page' => request('page')])) }}" class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-500 text-white px-3.5 py-2 rounded-xl font-semibold text-xs transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        Review Questions
                                    </a>

                                @elseif($item->status === 'approved')
                                    <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl p-1 pr-2">
                                        <input type="text" readonly value="{{ route('interview.public', $item->public_url ?? $item->id) }}" class="text-xs bg-transparent w-36 sm:w-48 text-slate-600 outline-none px-2 font-mono" onclick="this.select();">
                                        <a href="{{ route('interview.public', $item->public_url ?? $item->id) }}" target="_blank" class="w-8 h-8 flex items-center justify-center bg-white hover:bg-indigo-600 hover:text-white text-slate-600 rounded-lg border border-slate-200 transition-colors" title="Open Link">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <form action="{{ route('interviews.regenerate-link', $item->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl border border-transparent hover:border-indigo-200 transition-all" title="Regenerate Link">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('interviews.generate', array_filter(['interview' => $item->id, 'page' => request('page')])) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-xl border border-transparent hover:border-amber-200 transition-all" title="Regenerate Questions">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            </button>
                                        </form>
                                    </div>

                                @elseif($item->status === 'completed')
                                    <button type="button"
                                            data-action="show-transcript"
                                            data-name="{{ $item->candidate_name }}"
                                            data-details-url="{{ route('interviews.details', $item) }}"
                                            class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-xl font-semibold text-xs transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Transcript
                                    </button>

                                    @if($eval)
                                        <button type="button"
                                                data-action="show-evaluation"
                                                data-name="{{ $item->candidate_name }}"
                                                data-details-url="{{ route('interviews.details', $item) }}"
                                                class="inline-flex items-center gap-2 px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 rounded-xl font-semibold text-xs transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                            Full Report
                                        </button>
                                    @endif
                                @endif

                                <div class="w-px h-6 bg-slate-200 mx-0.5 hidden sm:block"></div>

                                <form action="{{ route('interviews.destroy', ['identifier' => $item->id]) }}" method="POST" onsubmit="return confirm('Delete this candidate permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-xl border border-transparent hover:border-rose-200 transition-all" title="Delete Candidate">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center border border-slate-200">
                                    <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                </div>
                                <div>
                                    <h4 class="text-slate-800 font-bold mb-1">No candidates found</h4>
                                    <p class="text-slate-500 text-sm">Import a CSV file to begin your recruitment flow.</p>
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
        {{ $interviews->links() }}
    </div>

    @push('scripts')
    <script>
        function openModal() {
            const modal = document.getElementById('data-modal');
            const content = document.getElementById('modal-content');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // Animate in
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeModal() {
            const modal = document.getElementById('data-modal');
            const content = document.getElementById('modal-content');
            // Animate out
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('data-modal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function decodeBase64Utf8(base64Str) {
            try {
                const binaryStr = atob(base64Str);
                const bytes = new Uint8Array(binaryStr.length);
                for (let i = 0; i < binaryStr.length; i++) {
                    bytes[i] = binaryStr.charCodeAt(i);
                }
                return new TextDecoder('utf-8').decode(bytes);
            } catch (err) {
                return base64Str;
            }
        }

        function showTranscript(name, text) {
            document.getElementById('modal-title').innerText = "Transcript: " + name;
            const body = document.getElementById('modal-body');
            const transcript = typeof text === 'string' ? text : '';

            if (!transcript || transcript.trim() === '') {
                body.innerHTML = '<div class="text-center text-slate-400 py-10">No transcript available</div>';
            } else {
                const lines = transcript.split('\n\n').filter(line => line.trim() !== '');
                let html = '<div class="space-y-6">';

                lines.forEach(line => {
                    const isCandidate = line.startsWith('Candidate:');
                    const cleanText = line.replace(/^(Candidate|AI):\s*/, '');

                    html += `
                        <div class="flex items-start gap-4 ${isCandidate ? 'flex-row-reverse' : 'flex-row'}">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 shadow-sm font-bold text-sm border ${
                                isCandidate ? 'bg-slate-100 text-slate-700 border-slate-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200'
                            }">
                                ${isCandidate ? 'C' : 'AI'}
                            </div>
                            <div class="max-w-[75%] p-5 rounded-[1.5rem] text-sm leading-relaxed shadow-sm ${
                                isCandidate ? 'bg-slate-100 text-slate-800 border border-slate-200 rounded-tr-sm' : 'bg-indigo-50 text-indigo-900 border border-indigo-100 rounded-tl-sm'
                            }">
                                ${cleanText.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                body.innerHTML = html;
            }

            openModal();
        }

        function showEvaluation(name, evaluationJson) {
            document.getElementById('modal-title').innerText = "Evaluation: " + name;
            const body = document.getElementById('modal-body');
            
            try {
                const evaluation = typeof evaluationJson === 'string'
                    ? JSON.parse(evaluationJson)
                    : evaluationJson;
                
                let html = `
                    <div class="space-y-8 text-slate-700">
                        <!-- Score & Summary -->
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-shrink-0 flex flex-col items-center justify-center w-32 h-32 rounded-3xl border ${
                                evaluation.score >= 8 ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 
                                (evaluation.score >= 5 ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-rose-50 border-rose-200 text-rose-700')
                            }">
                                <span class="text-4xl font-black">${evaluation.score}</span>
                                <span class="text-xs font-bold uppercase tracking-widest mt-1 opacity-80">/ 10</span>
                            </div>
                            <div class="flex-1 bg-slate-50 rounded-2xl p-6 border border-slate-200">
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    AI Summary
                                </h4>
                                <p class="text-sm leading-relaxed">${evaluation.summary}</p>
                            </div>
                        </div>

                        <!-- Strengths & Weaknesses -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-emerald-50 rounded-2xl p-6 border border-emerald-100">
                                <h4 class="text-xs font-bold text-emerald-700 uppercase tracking-wide mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    Strengths
                                </h4>
                                <ul class="space-y-3">
                                    ${(evaluation.strengths || []).map(s => `
                                        <li class="flex items-start gap-3 text-sm">
                                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span class="opacity-90">${s}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                            
                            <div class="bg-rose-50 rounded-2xl p-6 border border-rose-100">
                                <h4 class="text-xs font-bold text-rose-700 uppercase tracking-wide mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                                    Areas to Improve
                                </h4>
                                <ul class="space-y-3">
                                    ${(evaluation.weaknesses || []).map(w => `
                                        <li class="flex items-start gap-3 text-sm">
                                            <svg class="w-4 h-4 text-rose-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span class="opacity-90">${w}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
                body.innerHTML = html;
            } catch(e) {
                body.innerHTML = '<div class="text-center text-rose-700 py-10">Error parsing evaluation data.</div>';
            }
            
            openModal();
        }

        async function loadInterviewDetails(url) {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load interview details');
            }

            return response.json();
        }

        // Event listeners for data-action buttons
        document.addEventListener('click', async function(e) {
            const button = e.target.closest('[data-action]');
            if (!button) return;
            
            const action = button.getAttribute('data-action');
            const name = button.getAttribute('data-name');
            const detailsUrl = button.getAttribute('data-details-url');

            if (action === 'show-evaluation' || action === 'show-transcript') {
                document.getElementById('modal-title').innerText = action === 'show-transcript'
                    ? ('Transcript: ' + name)
                    : ('Evaluation: ' + name);
                document.getElementById('modal-body').innerHTML = '<div class="text-center text-slate-400 py-10">Loading…</div>';
                openModal();

                try {
                    const details = await loadInterviewDetails(detailsUrl);
                    if (action === 'show-transcript') {
                        showTranscript(name, details.transcript || '');
                    } else {
                        showEvaluation(name, details.evaluation || {});
                    }
                } catch (err) {
                    document.getElementById('modal-body').innerHTML = '<div class="text-center text-rose-700 py-10">Unable to load details.</div>';
                }
            } else if (action === 'show-photo') {
                const photo = button.getAttribute('data-photo');
                showPhoto(name, photo);
            }
        });
    </script>
    @endpush
</x-dark-layout>