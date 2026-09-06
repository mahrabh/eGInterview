<x-dark-layout>
    <div class="mb-5">
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Account Settings</h2>
        <p class="mt-1 text-sm text-slate-500">Manage your profile details and security preferences.</p>
    </div>

    @if(session('status') === 'settings-updated')
        <div class="flash-message mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl border border-emerald-200 text-sm font-semibold flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Account settings updated successfully.
        </div>
    @endif
    @if(session('error'))
        <div class="flash-message mb-4 bg-rose-50 text-rose-700 px-4 py-3 rounded-xl border border-rose-200 text-sm font-semibold flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
        <aside class="lg:col-span-4">
            <div class="glass-panel rounded-2xl overflow-hidden h-full">
                <div class="px-5 py-4 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white">
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white text-xl font-black shrink-0 border border-white/15">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold truncate">{{ Auth::user()->name }}</h3>
                            <p class="text-xs text-slate-300 truncate mt-0.5">{{ Auth::user()->email }}</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="inline-flex px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide rounded-full {{ Auth::user()->isAdmin() ? 'bg-indigo-400/20 text-indigo-100 border border-indigo-300/30' : (Auth::user()->isAnalyst() ? 'bg-cyan-400/20 text-cyan-100 border border-cyan-300/30' : 'bg-violet-400/20 text-violet-100 border border-violet-300/30') }}">
                            {{ Auth::user()->roleLabel() }}
                        </span>
                    </div>
                </div>

                <div class="p-5">
                    @if(!Auth::user()->isAdmin() && Auth::user()->plan)
                        @php
                            $used = Auth::user()->interviews()->count();
                            $limit = max(1, Auth::user()->plan->interview_limit);
                            $percent = min(100, ($used / $limit) * 100);
                        @endphp
                        <div class="mb-4 p-3.5 rounded-xl bg-indigo-50/70 border border-indigo-100">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-slate-700">Plan: {{ Auth::user()->plan->name }}</span>
                                <span class="text-sm font-semibold text-indigo-600">{{ $used }} / {{ Auth::user()->plan->interview_limit }}</span>
                            </div>
                            <div class="w-full bg-indigo-100 rounded-full h-2">
                                <div class="bg-indigo-500 h-2 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">Interview usage</p>
                        </div>
                    @endif

                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-500">Member since</span>
                            <span class="font-semibold text-slate-800">{{ Auth::user()->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-500">Status</span>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-700">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <section class="lg:col-span-8">
            <div class="glass-panel rounded-2xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Profile & Security</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Update your name, email, or password.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.update') }}" id="settings-form" class="p-5 sm:p-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-700 mb-1.5">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                                class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                placeholder="Your full name">
                            <x-input-error :messages="$errors->get('name')" class="mt-1.5 text-rose-600 text-sm" />
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                                class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                placeholder="your@email.com">
                            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-rose-600 text-sm" />
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-5">
                        <h4 class="text-sm font-bold text-slate-900 mb-0.5">Change password</h4>
                        <p class="text-xs text-slate-500 mb-4">Leave blank if you don't want to change your password.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="current_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Current password</label>
                                <input id="current_password" type="password" name="current_password"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="••••••••">
                                <x-input-error :messages="$errors->get('current_password')" class="mt-1.5 text-rose-600 text-sm" />
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">New password</label>
                                <input id="password" type="password" name="password"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Min. 8 characters">
                                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-rose-600 text-sm" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-slate-200">
                        <p id="settings-save-hint" class="text-xs text-slate-500 transition-opacity duration-200 opacity-0 pointer-events-none" aria-live="polite"></p>
                        <button type="submit" id="settings-save-btn" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 min-w-[150px]">
                            <svg id="settings-save-icon" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <svg id="settings-save-spinner" class="w-4 h-4 shrink-0 animate-spin hidden" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span id="settings-save-text">Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const form = document.getElementById('settings-form');
            if (!form) return;

            const btn = document.getElementById('settings-save-btn');
            const icon = document.getElementById('settings-save-icon');
            const spinner = document.getElementById('settings-save-spinner');
            const text = document.getElementById('settings-save-text');
            const hint = document.getElementById('settings-save-hint');
            const nameInput = form.querySelector('#name');
            const emailInput = form.querySelector('#email');
            const currentPassword = form.querySelector('#current_password');
            const newPassword = form.querySelector('#password');

            const initial = {
                name: (nameInput?.value || '').trim(),
                email: (emailInput?.value || '').trim(),
            };

            let hintTimer = null;
            let isSaving = false;

            function showHint(message) {
                if (!hint) return;
                hint.textContent = message;
                hint.classList.remove('opacity-0');
                hint.classList.add('opacity-100');
                clearTimeout(hintTimer);
                hintTimer = setTimeout(function () {
                    hint.classList.remove('opacity-100');
                    hint.classList.add('opacity-0');
                }, 2200);
            }

            function hasChanges() {
                const nameChanged = (nameInput?.value || '').trim() !== initial.name;
                const emailChanged = (emailInput?.value || '').trim() !== initial.email;
                const passwordTouched = !!(currentPassword?.value || newPassword?.value);
                return nameChanged || emailChanged || passwordTouched;
            }

            function setIdleButton() {
                if (!btn) return;
                isSaving = false;
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-wait', 'pointer-events-none');
                if (icon) icon.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (text) text.textContent = 'Save Changes';
            }

            function setSavingButton() {
                if (!btn || isSaving) return;
                isSaving = true;
                if (icon) icon.classList.add('hidden');
                if (spinner) spinner.classList.remove('hidden');
                if (text) text.textContent = 'Saving…';
                btn.classList.add('opacity-70', 'cursor-wait', 'pointer-events-none');
                // Disable after submit has started so the request is not cancelled.
                setTimeout(function () {
                    if (isSaving) btn.disabled = true;
                }, 0);
            }

            form.addEventListener('submit', function (event) {
                if (!hasChanges()) {
                    event.preventDefault();
                    setIdleButton();
                    showHint('No changes to save');
                    return;
                }

                setSavingButton();
            });

            // If user edits again after a blocked save, clear the hint.
            ['input', 'change'].forEach(function (evt) {
                form.addEventListener(evt, function () {
                    if (hint) {
                        hint.classList.add('opacity-0');
                        hint.classList.remove('opacity-100');
                    }
                });
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) setIdleButton();
            });
        })();
    </script>
</x-dark-layout>
