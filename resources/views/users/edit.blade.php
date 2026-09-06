<x-dark-layout>
    <div class="mb-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Edit User</h2>
            <p class="text-xs text-slate-500 mt-0.5 truncate">Update credentials for <span class="text-indigo-600 font-medium">{{ $user->name }}</span>.</p>
        </div>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('users.update', $user) }}" id="edit-user-form">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            {{-- Summary --}}
            <aside class="lg:col-span-4">
                <div class="glass-panel rounded-xl overflow-hidden h-full">
                    <div class="px-4 py-3 bg-gradient-to-br from-slate-50 via-indigo-50/50 to-cyan-50/40 border-b border-slate-200/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white text-sm font-black shrink-0">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 truncate">{{ $user->name }}</h3>
                                <p class="text-[11px] text-slate-500 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span class="inline-flex px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full border
                                {{ $user->isAnalyst() ? 'bg-cyan-50 text-cyan-700 border-cyan-200' : 'bg-violet-50 text-violet-700 border-violet-200' }}">
                                {{ $user->roleLabel() }}
                            </span>
                            @if($user->plan)
                                <span class="inline-flex px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-white border border-slate-200 text-slate-600">
                                    {{ $user->plan->name }}
                                </span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-slate-100 border border-slate-200 text-slate-500">
                                    No plan
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="p-3.5 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Workspace access</p>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Dashboard & interviews
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Role-based module access
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </span>
                            No Users & Plans access
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Form --}}
            <section class="lg:col-span-8">
                <div class="glass-panel rounded-xl overflow-hidden flex flex-col">
                    <div class="px-4 py-2.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Account credentials</h3>
                            <p class="text-[11px] text-slate-500">Login, role, and optional plan</p>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Full name</label>
                                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                <x-input-error :messages="$errors->get('name')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Email address</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                <x-input-error :messages="$errors->get('email')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">New password</label>
                                <input id="password" type="password" name="password"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Min. 8 characters">
                                <p class="text-[11px] text-slate-500 mt-1">Leave blank to keep current password.</p>
                                <x-input-error :messages="$errors->get('password')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1">Confirm new password</label>
                                <input id="password_confirmation" type="password" name="password_confirmation"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Re-enter new password">
                            </div>

                            <div>
                                <label for="role" class="block text-xs font-semibold text-slate-700 mb-1">Workspace role</label>
                                <select id="role" name="role" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                    <option value="analyst" {{ old('role', $user->role) === 'analyst' ? 'selected' : '' }}>Analyst — Loan Applicants only</option>
                                    <option value="recruiter" {{ old('role', $user->role) === 'recruiter' ? 'selected' : '' }}>Recruiter — Recruitment only</option>
                                </select>
                                <x-input-error :messages="$errors->get('role')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="plan_id" class="block text-xs font-semibold text-slate-700 mb-1">Assigned plan</label>
                                <select id="plan_id" name="plan_id"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                    <option value="">— No plan —</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" {{ old('plan_id', $user->plan_id) == $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }} — ৳{{ number_format($plan->price, 2) }}/mo
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('plan_id')" class="mt-1 text-rose-600 text-xs" />
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 px-4 py-3 border-t border-slate-200 bg-white/95 backdrop-blur-sm flex items-center justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors px-2 py-1.5">Cancel</a>
                        <button type="submit" id="edit-user-btn" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors min-w-[132px]">
                            <svg id="edit-user-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <svg id="edit-user-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span id="edit-user-text">Update User</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('edit-user-form');
            if (!form) return;

            const btn = document.getElementById('edit-user-btn');
            const icon = document.getElementById('edit-user-icon');
            const spinner = document.getElementById('edit-user-spinner');
            const text = document.getElementById('edit-user-text');

            function resetBtn() {
                if (!btn) return;
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed', 'pointer-events-none');
                if (icon) icon.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (text) text.textContent = 'Update User';
            }

            form.addEventListener('submit', function () {
                if (icon) icon.classList.add('hidden');
                if (spinner) spinner.classList.remove('hidden');
                if (text) text.textContent = 'Updating...';
                if (btn) btn.classList.add('opacity-80', 'pointer-events-none');
                setTimeout(function () {
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('cursor-not-allowed');
                    }
                }, 50);
                setTimeout(function () {
                    if (text && text.textContent === 'Updating...') resetBtn();
                }, 8000);
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) resetBtn();
            });
        })();
    </script>
</x-dark-layout>
