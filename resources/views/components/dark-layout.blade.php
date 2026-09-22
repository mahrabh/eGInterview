<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'eG Credit AI' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v=egmeet-1" type="image/svg+xml">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}?v=egmeet-1" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .app-shell-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(ellipse 80% 50% at 0% -10%, rgba(99, 102, 241, 0.08), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 0%, rgba(59, 130, 246, 0.06), transparent 50%),
                linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
            background-size: auto, auto, 48px 48px, 48px 48px;
        }
        .glass-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        /* Light AI content hero (navbar stays dark via .ai-nav) */
        .ai-hero {
            background:
                linear-gradient(135deg, #ffffff 0%, #f8fafc 40%, #eef2ff 78%, #f0f9ff 100%);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
        }
        .ai-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 16% 24%, rgba(99, 102, 241, 0.12), transparent 42%),
                radial-gradient(circle at 88% 14%, rgba(56, 189, 248, 0.10), transparent 38%),
                linear-gradient(rgba(99, 102, 241, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.06) 1px, transparent 1px);
            background-size: auto, auto, 32px 32px, 32px 32px;
            pointer-events: none;
        }
        /* Soft moving light — continuous, easy to notice, not flashy */
        .ai-hero::after {
            content: '';
            position: absolute;
            top: -30%;
            left: -40%;
            width: 55%;
            height: 160%;
            background: linear-gradient(
                105deg,
                transparent 0%,
                rgba(99, 102, 241, 0.08) 42%,
                rgba(56, 189, 248, 0.10) 50%,
                transparent 62%
            );
            pointer-events: none;
            animation: ai-hero-shimmer 7s ease-in-out infinite;
        }
        .ai-hero > * { position: relative; z-index: 1; }

        @keyframes ai-hero-shimmer {
            0% { transform: translateX(0); opacity: 0.45; }
            50% { opacity: 0.9; }
            100% { transform: translateX(160%); opacity: 0.45; }
        }
        @keyframes ai-hero-badge-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        @keyframes ai-hero-rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ai-hero-badge {
            animation: ai-hero-badge-float 3.2s ease-in-out infinite;
        }
        .ai-hero-rise {
            animation: ai-hero-rise 0.85s ease-out both;
        }
        .ai-hero-rise-2 {
            animation: ai-hero-rise 0.85s ease-out 0.18s both;
        }
        .ai-hero-rise-3 {
            animation: ai-hero-rise 0.85s ease-out 0.34s both;
        }
        .welcome-type-caret {
            display: inline-block;
            margin-left: 2px;
            color: #6366f1;
            font-weight: 400;
            animation: welcome-caret-blink 1s steps(1, end) infinite;
        }
        @keyframes welcome-caret-blink {
            0%, 45% { opacity: 1; }
            50%, 100% { opacity: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .ai-hero::after,
            .ai-hero-badge,
            .ai-hero-rise,
            .ai-hero-rise-2,
            .ai-hero-rise-3 {
                animation: none !important;
            }
            .welcome-type-caret { display: none !important; }
        }

        /* Dark AI sticky navbar */
        .ai-nav {
            background:
                linear-gradient(105deg, #0f172a 0%, #1e293b 42%, #312e81 78%, #0f172a 100%);
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.22);
        }
        .ai-nav::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 14% 40%, rgba(99, 102, 241, 0.28), transparent 42%),
                radial-gradient(circle at 90% 20%, rgba(56, 189, 248, 0.14), transparent 36%),
                linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
            background-size: auto, auto, 28px 28px, 28px 28px;
            pointer-events: none;
        }
        .ai-nav > * { position: relative; z-index: 1; }
        .ai-nav-link {
            color: rgba(203, 213, 225, 0.9);
        }
        .ai-nav-link:hover {
            color: #ffffff;
        }
        .ai-nav-link.is-active {
            color: #a5b4fc;
        }

        /* Hide scrollbar globally */
        * { scrollbar-width: none; -ms-overflow-style: none; }
        *::-webkit-scrollbar { display: none; }

        /* Custom scrollbar for modal only */
        .custom-scrollbar { scrollbar-width: thin; -ms-overflow-style: auto; }
        .custom-scrollbar::-webkit-scrollbar { display: block; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
    <script>
        function showTranscript(name, text) {
            document.getElementById('modal-title').innerText = (name || 'Applicant') + "'s Transcript";
            const displayText = text && String(text).trim() ? String(text) : 'No transcript available.';
            const body = document.getElementById('modal-body');
            body.replaceChildren();
            const pre = document.createElement('pre');
            pre.className = 'text-sm text-slate-700 whitespace-pre-wrap font-sans bg-slate-50 p-6 rounded-2xl border border-slate-200 leading-relaxed';
            pre.textContent = displayText;
            body.appendChild(pre);
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

            let scoreColor = data.score >= 8 ? 'text-emerald-700' : (data.score >= 5 ? 'text-amber-700' : 'text-rose-700');
            let scoreBg = data.score >= 8 ? 'bg-emerald-50 border-emerald-200' : (data.score >= 5 ? 'bg-amber-50 border-amber-200' : 'bg-rose-50 border-rose-200');

            let html = `
                <div class="flex items-center justify-between mb-8 pb-8 border-b border-slate-200">
                    <div>
                        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Final Score</h3>
                        <p class="text-xs text-slate-400">Based on technical accuracy</p>
                    </div>
                    <div class="w-24 h-24 rounded-2xl flex items-center justify-center ${scoreBg} border">
                        <span class="text-4xl font-black ${scoreColor}">${data.score}<span class="text-xl text-slate-400 opacity-60">/10</span></span>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200">
                        <h4 class="flex items-center gap-2 text-xs font-bold text-indigo-600 uppercase tracking-widest mb-3">
                            Executive Summary
                        </h4>
                        <p class="text-sm text-slate-700 leading-relaxed">${data.summary}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100">
                            <h4 class="text-xs font-bold text-emerald-700 uppercase tracking-widest mb-4">Key Strengths</h4>
                            <ul class="text-sm text-slate-700 space-y-3">
                                ${data.strengths?.map(s => `<li class="flex items-start gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></div><span>${s}</span></li>`).join('') || ''}
                            </ul>
                        </div>

                        <div class="bg-rose-50 p-6 rounded-2xl border border-rose-100">
                            <h4 class="text-xs font-bold text-rose-700 uppercase tracking-widest mb-4">Areas for Improvement</h4>
                            <ul class="text-sm text-slate-700 space-y-3">
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
                body.innerHTML = '<div class="text-center text-slate-500 py-10">No photo captured.</div>';
            } else {
                body.innerHTML = `
                    <div class="flex flex-col items-center justify-center gap-4 p-4">
                        <img src="${photoUrl}" alt="${name} photo" class="max-h-[70vh] w-auto rounded-2xl border border-slate-200 object-contain" />
                        <p class="text-sm text-slate-500">Click outside or press ESC to close.</p>
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

        function toggleMobileNav() {
            const panel = document.getElementById('mobile-nav-panel');
            const openIcon = document.getElementById('mobile-nav-open');
            const closeIcon = document.getElementById('mobile-nav-close');
            const isHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !isHidden);
            openIcon.classList.toggle('hidden', isHidden);
            closeIcon.classList.toggle('hidden', !isHidden);
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

        document.addEventListener('DOMContentLoaded', function() {
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(el => {
                el.style.transition = 'opacity 500ms ease-in-out';
                setTimeout(function() {
                    el.style.opacity = '0';
                    setTimeout(function() { el.remove(); }, 500);
                }, 5000);
            });
        });
    </script>
</head>

<body class="app-shell-bg min-h-screen text-slate-700 antialiased relative selection:bg-indigo-500/20">
    <!-- Sticky Header -->
    <header class="ai-nav sticky top-0 z-40 w-full">
        <div class="h-0.5 w-full bg-gradient-to-r from-indigo-500 via-blue-400 to-cyan-300"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <nav class="py-3 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group min-w-0">
                        <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center border border-white/20 group-hover:scale-105 transition-transform bg-gradient-to-br from-indigo-500 to-blue-600 overflow-hidden shadow-lg shadow-indigo-500/20">
                            <span class="text-white font-black text-base">eG</span>
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-lg sm:text-xl font-black text-white tracking-tight group-hover:text-indigo-200 transition-colors truncate leading-tight">eG Credit AI</h1>
                        </div>
                    </a>
                </div>

                {{-- Desktop nav --}}
                <div class="hidden lg:flex items-center gap-6">
                    <div class="flex items-center gap-5 text-sm font-semibold">
                        <a href="{{ route('dashboard') }}" class="ai-nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            Dashboard
                        </a>
                        @if(Auth::check() && Auth::user()->canAccessRecruitment())
                        <a href="{{ route('recruitment.index') }}" class="ai-nav-link {{ request()->routeIs('recruitment.*') || request()->routeIs('interviews.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            Recruitment
                        </a>
                        @endif
                        @if(Auth::check() && Auth::user()->canAccessLoans())
                        <a href="{{ route('loan-applications.index') }}" class="ai-nav-link {{ request()->routeIs('loan-applications.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Loan Applicants
                        </a>
                        @endif
                        @if(Auth::check() && Auth::user()->canAccessBankOpening())
                        <a href="{{ route('bank-openings.index') }}" class="ai-nav-link {{ request()->routeIs('bank-openings.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            Bank Account Opening
                        </a>
                        @endif
                        @if(Auth::check() && Auth::user()->isAdmin())
                        <a href="{{ route('users.index') }}" class="ai-nav-link {{ request()->routeIs('users.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            Users
                        </a>
                        <a href="{{ route('plans.index') }}" class="ai-nav-link {{ request()->routeIs('plans.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            Plans
                        </a>
                        @endif
                        <a href="{{ route('billing.index') }}" class="ai-nav-link {{ request()->routeIs('billing.*') ? 'is-active' : '' }} transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                            Billing
                        </a>
                    </div>

                    <div class="flex items-center gap-3 pl-6 border-l border-white/15">
                        <a href="{{ route('settings') }}" class="flex items-center gap-3 group" title="Account Settings">
                            <div class="text-right hidden xl:block">
                                <p class="text-sm font-bold text-white group-hover:text-indigo-200 transition-colors">{{ Auth::user()->name ?? 'User' }}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ Auth::user()->roleLabel() }}</p>
                            </div>
                            <span class="w-10 h-10 rounded-xl {{ request()->routeIs('settings') ? 'bg-indigo-500/30 border-indigo-300/40 text-indigo-100' : 'bg-white/10 border-white/15 text-slate-200 group-hover:bg-white/15 group-hover:text-white' }} flex items-center justify-center font-black border transition-all">
                                {{ substr(Auth::user()->name ?? 'U', 0, 1) }}
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-slate-300 hover:text-rose-300 hover:bg-rose-500/20 border border-white/15 hover:border-rose-400/30 transition-all" title="Logout">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Mobile: compact user actions + hamburger --}}
                <div class="flex lg:hidden items-center gap-2">
                    <a href="{{ route('settings') }}" class="w-9 h-9 rounded-xl {{ request()->routeIs('settings') ? 'bg-indigo-500/30 border-indigo-300/40 text-indigo-100' : 'bg-white/10 border-white/15 text-slate-200' }} flex items-center justify-center font-black border text-sm" title="Account Settings">
                        {{ substr(Auth::user()->name ?? 'U', 0, 1) }}
                    </a>
                    <button type="button" onclick="toggleMobileNav()" class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 border border-white/15 transition-colors" aria-label="Toggle navigation" aria-controls="mobile-nav-panel">
                        <svg id="mobile-nav-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <svg id="mobile-nav-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </nav>

            {{-- Mobile panel --}}
            <div id="mobile-nav-panel" class="hidden lg:hidden border-t border-white/10 pb-4 pt-2">
                <div class="flex flex-col gap-1 text-sm font-semibold">
                    <a href="{{ route('dashboard') }}" class="ai-nav-link {{ request()->routeIs('dashboard') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    @if(Auth::check() && Auth::user()->canAccessRecruitment())
                    <a href="{{ route('recruitment.index') }}" class="ai-nav-link {{ request()->routeIs('recruitment.*') || request()->routeIs('interviews.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Recruitment
                    </a>
                    @endif
                    @if(Auth::check() && Auth::user()->canAccessLoans())
                    <a href="{{ route('loan-applications.index') }}" class="ai-nav-link {{ request()->routeIs('loan-applications.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Loan Applicants
                    </a>
                    @endif
                    @if(Auth::check() && Auth::user()->canAccessBankOpening())
                    <a href="{{ route('bank-openings.index') }}" class="ai-nav-link {{ request()->routeIs('bank-openings.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Bank Account Opening
                    </a>
                    @endif
                    @if(Auth::check() && Auth::user()->isAdmin())
                    <a href="{{ route('users.index') }}" class="ai-nav-link {{ request()->routeIs('users.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Users
                    </a>
                    <a href="{{ route('plans.index') }}" class="ai-nav-link {{ request()->routeIs('plans.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Plans
                    </a>
                    @endif
                    <a href="{{ route('billing.index') }}" class="ai-nav-link {{ request()->routeIs('billing.*') ? 'is-active bg-white/10' : 'hover:bg-white/5 hover:text-white' }} rounded-xl px-3 py-2.5 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                        Billing
                    </a>
                </div>

                <div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-white truncate">{{ Auth::user()->name ?? 'User' }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ Auth::user()->roleLabel() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-slate-300 hover:text-rose-300 hover:bg-rose-500/20 border border-white/15 transition-all" title="Logout">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 pb-8 pt-6 relative z-10 flex-1 w-full">
        <main>
            @if (isset($header))
                <header class="mb-8 border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">{{ $header }}</h2>
                </header>
            @endif
            {{ $slot }}
        </main>
    </div>

    <!-- Data Modal -->
    <div id="data-modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
        <div id="modal-content" class="bg-white border border-slate-200 rounded-3xl shadow-xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden transform scale-95 opacity-0 transition-all duration-300">
            <div class="px-6 sm:px-8 py-5 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h2 id="modal-title" class="text-lg font-black text-slate-900 tracking-tight">Data View</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-rose-600 w-10 h-10 flex items-center justify-center bg-white hover:bg-rose-50 rounded-full border border-slate-200 hover:border-rose-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="modal-body" class="p-6 sm:p-8 overflow-y-auto flex-1 no-scrollbar"></div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
