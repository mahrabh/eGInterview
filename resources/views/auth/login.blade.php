<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | eGInterview AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        slate: { 850: '#151e2e', 900: '#0f172a', 950: '#020617' }
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
        /* Hide scrollbar for Chrome, Safari and Opera */
        ::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        html {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
</head>
<body class="bg-slate-950 min-h-screen text-slate-200 antialiased relative selection:bg-indigo-500/30 flex items-center justify-center">

    <!-- Ambient Background Glows -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-600/10 blur-[120px]"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-600/5 blur-[120px]"></div>
    </div>

    <div class="w-full max-w-md relative z-10 px-6">
        
        <div class="text-center mb-8 flex flex-col items-center">
            <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/20 border border-white/10 mb-4 overflow-hidden">
                <span class="text-white font-black text-xl">eG</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">eGInterview AI</h1>
        </div>

        <div class="glass-panel rounded-3xl p-8 sm:p-10 shadow-2xl">
            
            <!-- Session Status -->
            @if(session('status'))
            <div class="mb-6 bg-emerald-500/10 text-emerald-400 px-4 py-3 rounded-xl border border-emerald-500/20 font-bold text-sm">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">{{ __('Email Address') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" 
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium text-sm" placeholder="admin@example.com">
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ __('Password') }}</label>
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password" 
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium text-sm" placeholder="••••••••">
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label for="remember_me" class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative flex items-center justify-center">
                            <input id="remember_me" type="checkbox" name="remember" class="peer appearance-none w-4 h-4 border border-slate-600 rounded bg-slate-900/50 checked:bg-indigo-500 checked:border-indigo-500 transition-all cursor-pointer">
                            <svg class="absolute w-2.5 h-2.5 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-400 group-hover:text-slate-300 transition-colors">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300 transition-colors">
                            {{ __('Forgot Password?') }}
                        </a>
                    @endif
                </div>

                <div class="pt-5 mt-2">
                    <button type="submit" id="login-button" class="w-full flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 rounded-xl font-bold transition-all shadow-[0_0_15px_rgba(79,70,229,0.3)]">
                        <span id="button-text">{{ __('Authenticate') }}</span>
                        <svg id="button-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        <svg id="loading-spinner" class="w-4 h-4 animate-spin hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </div>
            </form>
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
                button.classList.remove('hover:bg-indigo-500');
                button.classList.add('bg-indigo-700', 'cursor-not-allowed');
                buttonText.textContent = 'Authenticating...';
                buttonIcon.classList.add('hidden');
                loadingSpinner.classList.remove('hidden');
            } else {
                button.disabled = false;
                button.classList.remove('bg-indigo-700', 'cursor-not-allowed');
                button.classList.add('hover:bg-indigo-500');
                buttonText.textContent = 'Authenticate';
                buttonIcon.classList.remove('hidden');
                loadingSpinner.classList.add('hidden');
            }
        }

        document.querySelector('form').addEventListener('submit', function() {
            setLoadingState(true);
        });
    </script>
</body>
</html>
