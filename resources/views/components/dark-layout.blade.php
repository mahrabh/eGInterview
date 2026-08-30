<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Recruiter Dashboard | eGInterview AI' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        slate: {
                            850: '#151e2e',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-panel {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Hide scrollbar globally */
        * { scrollbar-width: none; -ms-overflow-style: none; }
        *::-webkit-scrollbar { display: none; }

        /* Custom scrollbar for modal only */
        .custom-scrollbar { scrollbar-width: thin; -ms-overflow-style: auto; }
        .custom-scrollbar::-webkit-scrollbar { display: block; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
    <script>
        function showTranscript(name, text) {
            document.getElementById('modal-title').innerText = name + "'s Transcript";
            const displayText = text && text.trim() ? text : 'No transcript available.';
            document.getElementById('modal-body').innerHTML = `<pre class="text-sm text-slate-300 whitespace-pre-wrap font-mono bg-slate-900/50 p-6 rounded-2xl border border-slate-700/50 leading-relaxed shadow-inner">${displayText}</pre>`;
            document.getElementById('data-modal').classList.remove('hidden');
            document.getElementById('data-modal').classList.add('flex');
            setTimeout(() => {
                document.getElementById('modal-content').classList.remove('scale-95', 'opacity-0');
                document.getElementById('modal-content').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function showEvaluation(name, evalJson) {
            document.getElementById('modal-title').innerText = name + "'s AI Evaluation";
            let data = typeof evalJson === 'string' ? JSON.parse(evalJson) : evalJson;
            
            let scoreColor = data.score >= 8 ? 'text-emerald-400' : (data.score >= 5 ? 'text-amber-400' : 'text-rose-400');
            let scoreBg = data.score >= 8 ? 'bg-emerald-500/10 border-emerald-500/20' : (data.score >= 5 ? 'bg-amber-500/10 border-amber-500/20' : 'bg-rose-500/10 border-rose-500/20');

            let html = `
                <div class="flex items-center justify-between mb-8 pb-8 border-b border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-1">Final Score</h3>
                        <p class="text-xs text-slate-500">Based on technical accuracy</p>
                    </div>
                    <div class="w-24 h-24 rounded-[2rem] flex items-center justify-center ${scoreBg} border shadow-inner">
                        <span class="text-4xl font-black ${scoreColor}">${data.score}<span class="text-xl text-slate-500 opacity-50">/10</span></span>
                    </div>
                </div>
                
                <div class="space-y-8">
                    <div class="bg-slate-900/50 p-6 rounded-3xl border border-slate-800">
                        <h4 class="flex items-center gap-2 text-xs font-bold text-indigo-400 uppercase tracking-widest mb-3">
                            Executive Summary
                        </h4>
                        <p class="text-sm text-slate-300 leading-relaxed">${data.summary}</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-emerald-950/20 p-6 rounded-3xl border border-emerald-900/30">
                            <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-4">Key Strengths</h4>
                            <ul class="text-sm text-slate-300 space-y-3">
                                ${data.strengths?.map(s => `<li class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></div><span>${s}</span></li>`).join('') || ''}
                            </ul>
                        </div>
                        
                        <div class="bg-rose-950/20 p-6 rounded-3xl border border-rose-900/30">
                            <h4 class="text-xs font-bold text-rose-400 uppercase tracking-widest mb-4">Areas for Improvement</h4>
                            <ul class="text-sm text-slate-300 space-y-3">
                                ${data.weaknesses?.map(w => `<li class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-500 mt-1.5 shrink-0"></div><span>${w}</span></li>`).join('') || ''}
                            </ul>
                        </div>
                    </div>
                </div>`;
            document.getElementById('modal-body').innerHTML = html;
            document.getElementById('data-modal').classList.remove('hidden');
            document.getElementById('data-modal').classList.add('flex');
            setTimeout(() => {
                document.getElementById('modal-content').classList.remove('scale-95', 'opacity-0');
                document.getElementById('modal-content').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function showPhoto(name, photoUrl) {
            document.getElementById('modal-title').innerText = name + ' - Photo Preview';
            const body = document.getElementById('modal-body');

            if (!photoUrl || photoUrl.trim() === '') {
                body.innerHTML = '<div class="text-center text-slate-400 py-10">No photo captured.</div>';
            } else {
                body.innerHTML = `
                    <div class="flex flex-col items-center justify-center gap-4 p-4">
                        <img src="${photoUrl}" alt="${name} photo" class="max-h-[70vh] w-auto rounded-[2rem] border border-slate-700 shadow-2xl object-contain" />
                        <p class="text-sm text-slate-400">Click outside or press ESC to close.</p>
                    </div>
                `;
            }

            document.getElementById('data-modal').classList.remove('hidden');
            document.getElementById('data-modal').classList.add('flex');
            setTimeout(() => {
                document.getElementById('modal-content').classList.remove('scale-95', 'opacity-0');
                document.getElementById('modal-content').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeModal() {
            document.getElementById('modal-content').classList.remove('scale-100', 'opacity-100');
            document.getElementById('modal-content').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('data-modal').classList.add('hidden');
                document.getElementById('data-modal').classList.remove('flex');
            }, 200);
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('data-modal');
            if (modal.classList.contains('flex') && event.target === modal) {
                closeModal();
            }
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Global flash message auto-hide
        document.addEventListener('DOMContentLoaded', function() {
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(el => {
                setTimeout(function() {
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 500ms ease-in-out';
                    setTimeout(function() { el.remove(); }, 500);
                }, 2000);
            });
        });
    </script>
</head>

<body class="bg-slate-950 min-h-screen text-slate-200 antialiased relative selection:bg-indigo-500/30">
    <!-- Ambient Background Glows -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-600/10 blur-[120px]"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-600/5 blur-[120px]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8 space-y-10 relative z-10">

        <!-- Top Navigation -->
        <nav class="glass-panel rounded-[2rem] px-8 py-5 flex flex-col md:flex-row items-center justify-between shadow-2xl gap-4">
            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-4 group">
                    {{-- Logo placeholder: replace src with your transparent PNG --}}
                    <div class="w-12 h-12 rounded-[1.25rem] flex items-center justify-center shadow-lg shadow-indigo-500/20 border border-white/10 group-hover:scale-105 transition-transform bg-gradient-to-br from-indigo-500 to-blue-600 overflow-hidden">
                        {{-- <img src="/images/logo.png" alt="eGInterview AI" class="w-full h-full object-contain p-1"> --}}
                        <span class="text-white font-black text-lg">eG</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-white tracking-tight group-hover:text-indigo-300 transition-colors">eGInterview AI</h1>
                        <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-[0.2em] mt-1">Recruiter Terminal</p>
                    </div>
                </a>
            </div>

            <div class="flex flex-col md:flex-row items-center gap-6 w-full md:w-auto mt-4 md:mt-0">
                <div class="flex items-center gap-6 text-sm font-bold text-slate-400">
                    {{-- Dashboard: everyone --}}
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'text-indigo-400' : 'hover:text-white' }} transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    {{-- Users + Plans: admin only --}}
                    @if(Auth::check() && Auth::user()->isAdmin())
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'text-indigo-400' : 'hover:text-white' }} transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Users
                    </a>
                    <a href="{{ route('plans.index') }}" class="{{ request()->routeIs('plans.*') ? 'text-indigo-400' : 'hover:text-white' }} transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Plans
                    </a>
                    @endif
                </div>

                <div class="flex items-center gap-3 md:pl-6 md:border-l border-slate-800 w-full md:w-auto justify-between md:justify-start">
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-bold text-white">{{ Auth::user()->name ?? 'User' }}</p>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">{{ Auth::user()->isAdmin() ? 'Workspace Owner' : 'Recruiter' }}</p>
                        </div>
                        <a href="{{ route('settings') }}" class="w-10 h-10 rounded-2xl {{ request()->routeIs('settings') ? 'bg-indigo-500/10 border-indigo-500/30 text-indigo-400' : 'bg-slate-800 border-slate-700 text-slate-300 hover:text-indigo-400 hover:bg-indigo-500/10 hover:border-indigo-500/20' }} flex items-center justify-center font-black border shadow-inner transition-all" title="Account Settings">
                            {{ substr(Auth::user()->name ?? 'U', 0, 1) }}
                        </a>
                    </div>
                    {{-- Settings icon --}}
                    <a href="{{ route('settings') }}" class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-slate-500 hover:text-indigo-400 hover:bg-indigo-500/10 border border-slate-800 hover:border-indigo-500/20 transition-all" title="Account Settings">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </a>
                    {{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-10 h-10 rounded-2xl bg-slate-900 flex items-center justify-center text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 border border-slate-800 hover:border-rose-500/20 transition-all" title="Logout">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main>
            @if (isset($header))
                <header class="mb-8 border-b border-slate-800 pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <h2 class="text-2xl font-black text-white tracking-tight">{{ $header }}</h2>
                </header>
            @endif
            {{ $slot }}
        </main>
    </div>

    <!-- Data Modal -->
    <div id="data-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div id="modal-content" class="bg-slate-900 border border-slate-800 rounded-[2.5rem] shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden transform scale-95 opacity-0 transition-all duration-300">
            <div class="px-8 py-6 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
                <h2 id="modal-title" class="text-lg font-black text-white tracking-tight">Data View</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-rose-400 w-10 h-10 flex items-center justify-center bg-slate-800 hover:bg-rose-500/10 rounded-full border border-transparent hover:border-rose-500/20 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="modal-body" class="p-8 overflow-y-auto flex-1 no-scrollbar"></div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
