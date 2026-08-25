<x-dark-layout>
    <!-- Header Actions -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
        <div>
            <h2 class="text-4xl font-black text-white tracking-tight">Candidates Overview</h2>
            <p class="text-slate-400 mt-2 text-sm font-medium">Manage imports, Questions generation, and final evaluations.</p>
        </div>
        
        <div class="flex items-center gap-4">
            @if(!auth()->user()->isAdmin() && auth()->user()->plan)
                <div class="glass-panel px-4 py-2.5 rounded-xl border border-indigo-500/20 flex items-center gap-2">
                    <span class="text-xs font-bold text-indigo-400 uppercase tracking-widest">{{ auth()->user()->plan->name }}: {{ auth()->user()->interviews()->count() }} / {{ auth()->user()->plan->interview_limit }} Used</span>
                </div>
            @endif

            <div class="glass-panel px-4 py-2.5 rounded-xl border border-slate-800 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-bold text-slate-300 uppercase tracking-widest">{{ $interviews->total() }} Records</span>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="{{ route('interviews.download-template') }}" class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 border border-transparent rounded-xl font-bold text-xs text-white uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(59,130,246,0.3)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Template
                </a>
            </div>
            
            <form action="{{ route('interviews.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center group relative">
                @csrf
                <label class="cursor-pointer glass-panel border border-indigo-500/30 hover:border-indigo-500/60 bg-indigo-500/5 hover:bg-indigo-500/10 px-5 py-3 rounded-2xl flex items-center gap-3 transition-all">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span class="text-sm font-bold text-indigo-300">Import CSV</span>
                    <input type="file" name="file" accept=".csv,.xlsx" required class="hidden" onchange="this.form.submit()">
                </label>
            </form>
        </div>
    </div>

    <!-- Alerts -->
    @if(session('success'))
    <div id="flash-success" class="flash-message bg-emerald-500/10 text-emerald-400 px-6 py-4 rounded-2xl border border-emerald-500/20 font-bold text-sm flex items-center gap-3 mb-6 transition-opacity duration-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div id="flash-error" class="flash-message bg-rose-500/10 text-rose-400 px-6 py-4 rounded-2xl border border-rose-500/20 font-bold text-sm flex items-center gap-3 mb-6 transition-opacity duration-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ $errors->first() }}
    </div>
    @endif

    <!-- Data Grid -->
    <div class="glass-panel border border-slate-800 rounded-[2rem] overflow-hidden shadow-2xl relative">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-800/50 bg-slate-900/50">
                    <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest w-1/3">Candidate</th>
                    <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest w-1/4">Status</th>
                    <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/50">
                @forelse($interviews as $item)
                @php
                    $eval = is_string($item->evaluation_json) ? json_decode($item->evaluation_json, true) : $item->evaluation_json;
                    $score = $eval['score'] ?? null;
                @endphp
                <tr class="hover:bg-slate-800/20 transition-colors group">
                    <td class="px-8 py-6">
                        <div class="flex items-center gap-5">
                            @if($item->candidate_photo)
                                <button type="button" 
                                        data-action="show-photo" 
                                        data-name="{{ $item->candidate_name }}" 
                                        data-photo="{{ $item->candidate_photo }}" 
                                        class="group rounded-[1.25rem] overflow-hidden border border-slate-700 shadow-inner transition-transform hover:-translate-y-0.5 focus:outline-none">
                                    <img src="{{ $item->candidate_photo }}" alt="{{ $item->candidate_name }}'s photo" class="w-12 h-12 object-cover">
                                </button>
                            @else
                                <div class="w-12 h-12 rounded-[1.25rem] bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 font-black text-xl shadow-inner">
                                    {{ strtoupper(substr($item->candidate_name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <h3 class="font-bold text-white text-base">{{ $item->candidate_name }}</h3>
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-1">{{ $item->applied_role }}</p>
                                @if(auth()->user()->isAdmin() && $item->user)
                                <p class="text-[10px] text-indigo-400 mt-1 font-bold flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                    Added by: {{ $item->user->name }}
                                </p>
                                @endif
                                @unless($item->candidate_photo)
                                <p class="text-[10px] text-slate-500 mt-1">No photo captured.</p>
                                @endunless
                            </div>
                        </div>
                    </td>

                    <td class="px-8 py-6">
                        <div class="flex items-center gap-3">
                            @if($item->status === 'draft')
                            <span class="inline-flex px-4 py-1.5 text-xs font-bold bg-slate-800 border border-slate-700 text-slate-400 rounded-full">Awaiting Setup</span>
                            @elseif($item->status === 'approved')
                            <span class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                                Link Active
                            </span>
                            @elseif($item->status === 'completed')
                            <span class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                Completed
                            </span>
                            @if($score !== null)
                            <span class="inline-flex px-4 py-1.5 text-xs font-black rounded-full border {{ $score >= 8 ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : ($score >= 5 ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20') }}">
                                Score: {{ $score }}/10
                            </span>
                            @endif
                            @endif
                        </div>
                    </td>

                    <td class="px-8 py-6">
                        <div class="flex items-center justify-end gap-3 opacity-80 group-hover:opacity-100 transition-opacity">
                            @if($item->status === 'draft')
                            <form action="{{ route('interviews.generate', $item->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold text-xs transition-all shadow-[0_0_15px_rgba(79,70,229,0.3)]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                    Generate  Questions
                                </button>
                            </form>
                            
                            @elseif($item->status === 'approved')
                            <div class="flex items-center gap-2 bg-slate-900 border border-slate-700 rounded-xl p-1.5 pr-3">
                                <input type="text" readonly value="{{ route('interview.public', $item->public_url ?? $item->id) }}" class="text-xs bg-transparent w-48 text-slate-400 outline-none px-3 font-mono" onclick="this.select();">
                                <a href="{{ route('interview.public', $item->public_url ?? $item->id) }}" target="_blank" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-indigo-500 hover:text-white text-slate-300 rounded-lg transition-colors" title="Open Link">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>
                            </div>
                            <div class="flex items-center gap-1">
                                <form action="{{ route('interviews.regenerate-link', $item->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-xl border border-transparent hover:border-indigo-500/20 transition-all" title="Regenerate Link">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    </button>
                                </form>
                                <form action="{{ route('interviews.generate', $item->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-amber-400 hover:bg-amber-500/10 rounded-xl border border-transparent hover:border-amber-500/20 transition-all" title="Regenerate Questions">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    </button>
                                </form>
                            </div>
                            
                            @elseif($item->status === 'completed')
                            <div class="flex items-center gap-3">
                                <button type="button" 
                                        data-action="show-transcript" 
                                        data-name="{{ $item->candidate_name }}" 
                                        data-transcript="{{ base64_encode($item->transcript_text ?? '') }}" 
                                        class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 rounded-xl font-bold text-xs transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Transcript
                                </button>
                                
                                @if($eval)
                                <button type="button" 
                                        data-action="show-evaluation" 
                                        data-name="{{ $item->candidate_name }}" 
                                        data-evaluation="{{ base64_encode(json_encode($eval)) }}" 
                                        class="flex items-center gap-2 px-4 py-2 bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 text-indigo-400 rounded-xl font-bold text-xs transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                    Full Report
                                </button>
                                @endif
                            </div>
                            @endif

                            <div class="w-px h-6 bg-slate-800 mx-1"></div>

                            <form action="{{ route('interviews.destroy', ['identifier' => $item->id]) }}" method="POST" onsubmit="return confirm('Delete this candidate permanently?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl border border-transparent hover:border-rose-500/20 transition-all" title="Delete Candidate">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-8 py-20 text-center">
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <div class="w-16 h-16 rounded-[2rem] bg-slate-800/50 flex items-center justify-center border border-slate-700">
                                <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-slate-300 font-bold mb-1">No candidates found</h4>
                                <p class="text-slate-500 text-sm">Import a CSV file to begin your recruitment flow.</p>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
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
            let transcript = '';

            if (typeof text === 'string' && text !== '') {
                transcript = decodeBase64Utf8(text);
            }

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
                                isCandidate ? 'bg-slate-800 text-slate-300 border-slate-700' : 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30'
                            }">
                                ${isCandidate ? 'C' : 'AI'}
                            </div>
                            <div class="max-w-[75%] p-5 rounded-[1.5rem] text-sm leading-relaxed shadow-sm ${
                                isCandidate ? 'bg-slate-800/80 text-white border border-slate-700/50 rounded-tr-sm' : 'bg-indigo-500/10 text-indigo-50 border border-indigo-500/20 rounded-tl-sm'
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
                let evaluationString = evaluationJson;
                if (typeof evaluationJson === 'string' && evaluationJson !== '') {
                    evaluationString = decodeBase64Utf8(evaluationJson);
                }
                const evaluation = typeof evaluationString === 'string' ? JSON.parse(evaluationString) : evaluationString;
                
                let html = `
                    <div class="space-y-8 text-slate-300">
                        <!-- Score & Summary -->
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-shrink-0 flex flex-col items-center justify-center w-32 h-32 rounded-3xl border shadow-inner ${
                                evaluation.score >= 8 ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 
                                (evaluation.score >= 5 ? 'bg-amber-500/10 border-amber-500/20 text-amber-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400')
                            }">
                                <span class="text-4xl font-black">${evaluation.score}</span>
                                <span class="text-xs font-bold uppercase tracking-widest mt-1 opacity-80">/ 10</span>
                            </div>
                            <div class="flex-1 bg-slate-800/30 rounded-3xl p-6 border border-slate-800">
                                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    AI Summary
                                </h4>
                                <p class="text-sm leading-relaxed">${evaluation.summary}</p>
                            </div>
                        </div>

                        <!-- Strengths & Weaknesses -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-emerald-500/5 rounded-3xl p-6 border border-emerald-500/10">
                                <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    Strengths
                                </h4>
                                <ul class="space-y-3">
                                    ${evaluation.strengths.map(s => `
                                        <li class="flex items-start gap-3 text-sm">
                                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span class="opacity-90">${s}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>
                            
                            <div class="bg-rose-500/5 rounded-3xl p-6 border border-rose-500/10">
                                <h4 class="text-xs font-bold text-rose-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                                    Areas to Improve
                                </h4>
                                <ul class="space-y-3">
                                    ${evaluation.weaknesses.map(w => `
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
                body.innerHTML = '<div class="text-center text-rose-400 py-10">Error parsing evaluation data.</div>';
            }
            
            openModal();
        }

        // Event listeners for data-action buttons
        document.addEventListener('click', function(e) {
            const button = e.target.closest('[data-action]');
            if (!button) return;
            
            const action = button.getAttribute('data-action');
            if (action === 'show-evaluation') {
                const name = button.getAttribute('data-name');
                const evaluation = button.getAttribute('data-evaluation');
                showEvaluation(name, evaluation);
            } else if (action === 'show-transcript') {
                const name = button.getAttribute('data-name');
                const transcript = button.getAttribute('data-transcript');
                showTranscript(name, transcript);
            } else if (action === 'show-photo') {
                const name = button.getAttribute('data-name');
                const photo = button.getAttribute('data-photo');
                showPhoto(name, photo);
            }
        });
    </script>
    @endpush
</x-dark-layout>