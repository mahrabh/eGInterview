<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | eGInterview AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        .login-wallpaper {
            background-color: #020617;
            background-image: url("{{ asset('images/login-ai-wallpaper.webp') }}");
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }

        .login-overlay {
            background:
                linear-gradient(180deg, rgba(2, 6, 23, 0.55) 0%, rgba(2, 6, 23, 0.72) 45%, rgba(2, 6, 23, 0.82) 100%),
                radial-gradient(ellipse 70% 55% at 50% 40%, rgba(2, 6, 23, 0.25), rgba(2, 6, 23, 0.65) 75%);
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 20px 50px rgba(2, 6, 23, 0.45);
        }

        .btn-ai {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #2563eb 100%);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
        }
        .btn-ai:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(79, 70, 229, 0.45);
            filter: brightness(1.05);
        }
        .btn-ai:disabled { opacity: 0.85; cursor: not-allowed; }

        .input-ai:focus {
            border-color: rgba(99, 102, 241, 0.75);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        }

        /* Compact AI equalizer */
        .eq {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 3px;
            height: 18px;
        }
        .eq span {
            width: 3px;
            border-radius: 99px;
            background: linear-gradient(180deg, #67e8f9 0%, #6366f1 100%);
            transform-origin: bottom;
            animation: eqBounce 1.05s ease-in-out infinite;
        }
        .eq span:nth-child(1) { height: 6px;  animation-delay: 0.00s; }
        .eq span:nth-child(2) { height: 12px; animation-delay: 0.08s; }
        .eq span:nth-child(3) { height: 18px; animation-delay: 0.16s; }
        .eq span:nth-child(4) { height: 10px; animation-delay: 0.24s; }
        .eq span:nth-child(5) { height: 16px; animation-delay: 0.12s; }
        .eq span:nth-child(6) { height: 8px;  animation-delay: 0.28s; }
        .eq span:nth-child(7) { height: 14px; animation-delay: 0.04s; }
        .eq span:nth-child(8) { height: 7px;  animation-delay: 0.20s; }

        @keyframes eqBounce {
            0%, 100% { transform: scaleY(0.45); opacity: 0.65; }
            50% { transform: scaleY(1); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.45s ease-out both; }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.45; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.08); }
        }
        .logo-glow { animation: glowPulse 3.2s ease-in-out infinite; }
    </style>
</head>
<body class="login-wallpaper min-h-screen text-slate-200 antialiased relative selection:bg-indigo-500/30 overflow-x-hidden">

    <div class="fixed inset-0 login-overlay pointer-events-none" aria-hidden="true"></div>

    <div class="relative z-10 min-h-screen flex items-center justify-center px-4 py-6 sm:py-8">
        <div class="w-full max-w-[400px] fade-in">

            {{-- Compact brand --}}
            <div class="text-center mb-5">
                <div class="inline-flex items-center gap-3">
                    <div class="relative">
                        <div class="logo-glow absolute inset-0 rounded-xl bg-indigo-500/50 blur-md"></div>
                        <div class="relative w-11 h-11 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-xl flex items-center justify-center border border-white/15">
                            <span class="text-white font-black text-lg">eG</span>
                        </div>
                    </div>
                    <div class="text-left">
                        <h1 class="text-xl font-black text-white tracking-tight leading-tight">eGInterview AI</h1>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">AI Interview</p>
                            <div class="eq" aria-hidden="true">
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card --}}
            <div class="glass-card rounded-2xl p-5 sm:p-6">
                <div class="mb-4">
                    <h2 class="text-base font-bold text-white">Sign in</h2>
                    <p class="text-xs text-slate-400 mt-1">Live interviews, evaluations &amp; assessments.</p>
                </div>

                @if(session('status'))
                <div class="mb-4 bg-emerald-500/10 text-emerald-300 px-3 py-2.5 rounded-xl border border-emerald-500/20 font-semibold text-xs">
                    {{ session('status') }}
                </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-3.5" id="login-form">
                    @csrf

                    <div>
                        <label for="email" class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">{{ __('Email Address') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            class="input-ai w-full bg-slate-950/70 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-slate-100 placeholder-slate-500 focus:outline-none transition-all font-medium text-sm"
                            placeholder="admin@example.com">
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-rose-400 text-xs font-semibold" />
                    </div>

                    <div>
                        <label for="password" class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">{{ __('Password') }}</label>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="input-ai w-full bg-slate-950/70 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-slate-100 placeholder-slate-500 focus:outline-none transition-all font-medium text-sm"
                            placeholder="••••••••">
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-rose-400 text-xs font-semibold" />
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-0.5">
                        <label for="remember_me" class="flex items-center gap-2 cursor-pointer group">
                            <div class="relative flex items-center justify-center">
                                <input id="remember_me" type="checkbox" name="remember" class="peer appearance-none w-4 h-4 border border-slate-600 rounded bg-slate-900/70 checked:bg-indigo-500 checked:border-indigo-500 transition-all cursor-pointer">
                                <svg class="absolute w-2.5 h-2.5 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-400 group-hover:text-slate-200 transition-colors">{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-indigo-300 hover:text-indigo-200 transition-colors">
                                {{ __('Forgot Password?') }}
                            </a>
                        @endif
                    </div>

                    <button type="submit" id="login-button" class="btn-ai w-full flex justify-center items-center gap-2 text-white px-4 py-2.5 rounded-xl font-bold text-sm mt-1">
                        <span id="button-text">{{ __('Authenticate') }}</span>
                        <svg id="button-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        <svg id="loading-spinner" class="w-4 h-4 animate-spin hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </form>
            </div>

            <p class="mt-4 text-center text-[11px] text-slate-500">
                Secure access to your AI interview workspace
            </p>
        </div>
    </div>

    <script>
        function setLoadingState(loading) {
            const button = document.getElementById('login-button');
            const buttonText = document.getElementById('button-text');
            const buttonIcon = document.getElementById('button-icon');
            const loadingSpinner = document.getElementById('loading-spinner');

            if (loading) {
                button.disabled = true;
                buttonText.textContent = 'Authenticating...';
                buttonIcon.classList.add('hidden');
                loadingSpinner.classList.remove('hidden');
            } else {
                button.disabled = false;
                buttonText.textContent = 'Authenticate';
                buttonIcon.classList.remove('hidden');
                loadingSpinner.classList.add('hidden');
            }
        }

        document.getElementById('login-form').addEventListener('submit', function () {
            setLoadingState(true);
        });
    </script>
</body>
</html>
