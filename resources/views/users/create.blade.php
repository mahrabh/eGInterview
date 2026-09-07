<x-dark-layout>
    <div class="max-w-4xl mx-auto mb-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Create User</h2>
            <p class="text-xs text-slate-500 mt-0.5 truncate">Add a workspace user with Recruiter, Analyst, or both.</p>
        </div>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('users.store') }}" id="create-user-form">
        @csrf

        <div class="max-w-4xl mx-auto">
            <div class="glass-panel rounded-xl overflow-hidden flex flex-col">
                <div class="px-4 py-2.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Account credentials</h3>
                        <p class="text-[11px] text-slate-500">Login, roles, and optional plan</p>
                    </div>
                </div>

                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                placeholder="John Smith">
                            <x-input-error :messages="$errors->get('name')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">Email address</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="recruiter@company.com">
                                <x-input-error :messages="$errors->get('email')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">Password</label>
                                <input id="password" type="password" name="password" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Min. 8 characters">
                                <x-input-error :messages="$errors->get('password')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1">Confirm password</label>
                                <input id="password_confirmation" type="password" name="password_confirmation" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Re-enter password">
                            </div>

                            <div class="md:col-span-2">
                                <p class="block text-xs font-semibold text-slate-700 mb-2">Workspace roles</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @php
                                        $selectedRoles = old('roles', ['analyst']);
                                        if (!is_array($selectedRoles)) {
                                            $selectedRoles = [$selectedRoles];
                                        }
                                    @endphp
                                    <label class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50 hover:border-indigo-200 cursor-pointer">
                                        <input type="checkbox" name="roles[]" value="recruiter" class="role-checkbox mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30" {{ in_array('recruiter', $selectedRoles, true) ? 'checked' : '' }}>
                                        <span>
                                            <span class="block text-xs font-semibold text-slate-900">Recruiter</span>
                                            <span class="block text-[11px] text-slate-500 mt-0.5">Recruitment panel access</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 bg-slate-50 hover:border-indigo-200 cursor-pointer">
                                        <input type="checkbox" name="roles[]" value="analyst" class="role-checkbox mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30" {{ in_array('analyst', $selectedRoles, true) ? 'checked' : '' }}>
                                        <span>
                                            <span class="block text-xs font-semibold text-slate-900">Analyst</span>
                                            <span class="block text-[11px] text-slate-500 mt-0.5">Loan Applicants panel access</span>
                                        </span>
                                    </label>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1.5">Select both for Combined plan users who need recruitment and loan access.</p>
                                <x-input-error :messages="$errors->get('roles')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div class="md:col-span-2">
                                <label for="plan_id" class="block text-xs font-semibold text-slate-700 mb-1">Plan assignment</label>
                                <select id="plan_id" name="plan_id"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                    <option value="">— No plan assigned —</option>
                                    @foreach($plans as $plan)
                                        <option
                                            value="{{ $plan->id }}"
                                            data-roles="{{ implode(',', $plan->moduleTypeEnum()->compatibleRoles()) }}"
                                            {{ old('plan_id') == $plan->id ? 'selected' : '' }}
                                        >
                                            {{ $plan->name }} · {{ $plan->moduleLabel() }} — ${{ number_format($plan->price, 2) }}/mo
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('plan_id')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <x-expiry-date-field />
                        </div>
                    </div>

                    {{-- Actions stay visible near bottom of card / screen --}}
                    <div class="sticky bottom-0 px-4 py-3 border-t border-slate-200 bg-white/95 backdrop-blur-sm flex items-center justify-end gap-3">
                        <a href="{{ route('users.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors px-2 py-1.5">Cancel</a>
                        <button type="submit" id="create-user-btn" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors min-w-[132px]">
                            <svg id="create-user-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            <svg id="create-user-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span id="create-user-text">Create User</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('create-user-form');
            if (!form) return;

            const btn = document.getElementById('create-user-btn');
            const icon = document.getElementById('create-user-icon');
            const spinner = document.getElementById('create-user-spinner');
            const text = document.getElementById('create-user-text');
            const roleBoxes = Array.from(form.querySelectorAll('.role-checkbox'));
            const planSelect = document.getElementById('plan_id');

            function selectedRoleValue() {
                const selected = roleBoxes.filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
                if (selected.indexOf('recruiter') !== -1 && selected.indexOf('analyst') !== -1) return 'both';
                if (selected.length === 1) return selected[0];
                return '';
            }

            function filterPlans() {
                if (!planSelect) return;
                const role = selectedRoleValue();
                Array.from(planSelect.options).forEach(function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }
                    if (!role) {
                        option.hidden = false;
                        return;
                    }
                    const roles = (option.getAttribute('data-roles') || '').split(',');
                    const compatible = roles.indexOf(role) !== -1;
                    option.hidden = !compatible;
                    if (!compatible && option.selected) {
                        planSelect.value = '';
                    }
                });
            }

            roleBoxes.forEach(function (box) {
                box.addEventListener('change', filterPlans);
            });
            filterPlans();

            function resetBtn() {
                if (!btn) return;
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed', 'pointer-events-none');
                if (icon) icon.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (text) text.textContent = 'Create User';
            }

            form.addEventListener('submit', function () {
                if (icon) icon.classList.add('hidden');
                if (spinner) spinner.classList.remove('hidden');
                if (text) text.textContent = 'Creating...';
                if (btn) btn.classList.add('opacity-80', 'pointer-events-none');
                setTimeout(function () {
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('cursor-not-allowed');
                    }
                }, 50);
                setTimeout(function () {
                    if (text && text.textContent === 'Creating...') resetBtn();
                }, 8000);
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) resetBtn();
            });
        })();
    </script>
</x-dark-layout>
