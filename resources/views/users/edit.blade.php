<x-dark-layout>
    <div class="max-w-4xl mx-auto mb-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Edit User</h2>
            <p class="text-xs text-slate-500 mt-0.5 truncate">Update credentials for <span class="text-indigo-600 font-medium">{{ $user->name }}</span>.</p>
        </div>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back
        </a>
    </div>

    @if(session('info'))
        <div class="max-w-4xl mx-auto mb-3 flash-message bg-amber-50 text-amber-800 px-4 py-2.5 rounded-lg border border-amber-200 text-xs font-semibold">
            {{ session('info') }}
        </div>
    @endif

    <form method="POST" action="{{ route('users.update', $user) }}" id="edit-user-form">
        @csrf
        @method('PUT')

        <div class="max-w-4xl mx-auto">
            <div class="glass-panel rounded-xl overflow-hidden flex flex-col">
                <div class="px-4 py-2.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-black">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 truncate">{{ $user->name }}</h3>
                        <p class="text-[11px] text-slate-500 truncate">{{ $user->email }} · {{ $user->roleLabel() }}</p>
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

                            <div class="md:col-span-2">
                                <p class="block text-xs font-semibold text-slate-700 mb-2">Workspace roles</p>
                                @php
                                    $defaultRoles = match ($user->role) {
                                        'both' => ['recruiter', 'analyst'],
                                        'recruiter' => ['recruiter'],
                                        default => ['analyst'],
                                    };
                                    $selectedRoles = old('roles', $defaultRoles);
                                    if (!is_array($selectedRoles)) {
                                        $selectedRoles = [$selectedRoles];
                                    }
                                @endphp
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
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
                                <label for="plan_id" class="block text-xs font-semibold text-slate-700 mb-1">Assigned plan</label>
                                <select id="plan_id" name="plan_id"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                    <option value="">— No plan —</option>
                                    @foreach($plans as $plan)
                                        <option
                                            value="{{ $plan->id }}"
                                            data-roles="{{ implode(',', $plan->moduleTypeEnum()->compatibleRoles()) }}"
                                            data-active="{{ $plan->is_active ? '1' : '0' }}"
                                            {{ old('plan_id', $user->plan_id) == $plan->id ? 'selected' : '' }}
                                        >
                                            {{ $plan->name }} · {{ $plan->moduleLabel() }}{{ $plan->is_active ? '' : ' (inactive)' }} — ${{ number_format($plan->price, 2) }}/mo
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('plan_id')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <x-expiry-date-field :value="old('expires_at', optional($user->expires_at)->format('Y-m-d'))" />
                        </div>
                    </div>

                    <div class="sticky bottom-0 px-4 py-3 border-t border-slate-200 bg-white/95 backdrop-blur-sm flex flex-col gap-2">
                        <p id="edit-user-hint" class="hidden text-xs font-semibold text-amber-700 text-right">No changes to save.</p>
                        <div class="flex items-center justify-end gap-3">
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
                </div>
            </div>
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
            const hint = document.getElementById('edit-user-hint');
            const roleBoxes = Array.from(form.querySelectorAll('.role-checkbox'));
            const planSelect = document.getElementById('plan_id');
            const currentPlanId = @json(old('plan_id', $user->plan_id));

            function serializeForm(target) {
                const data = new FormData(target);
                const pairs = [];
                data.forEach(function (value, key) {
                    if (key === '_token' || key === '_method') return;
                    pairs.push(key + '=' + String(value));
                });
                pairs.sort();
                return pairs.join('&');
            }

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
                    const roles = (option.getAttribute('data-roles') || '').split(',');
                    const isActive = option.getAttribute('data-active') !== '0';
                    const isCurrent = String(option.value) === String(currentPlanId || '');
                    const compatible = !role || roles.indexOf(role) !== -1;
                    option.hidden = !(compatible && (isActive || isCurrent));
                    if (option.hidden && option.selected) {
                        planSelect.value = '';
                    }
                });
            }

            roleBoxes.forEach(function (box) {
                box.addEventListener('change', filterPlans);
            });
            filterPlans();

            const initialSnapshot = serializeForm(form);

            function showNoChanges() {
                if (!hint) return;
                hint.classList.remove('hidden');
                clearTimeout(showNoChanges._timer);
                showNoChanges._timer = setTimeout(function () {
                    hint.classList.add('hidden');
                }, 2500);
            }

            function resetBtn() {
                if (!btn) return;
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed', 'pointer-events-none');
                if (icon) icon.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (text) text.textContent = 'Update User';
            }

            form.addEventListener('submit', function (event) {
                if (serializeForm(form) === initialSnapshot) {
                    event.preventDefault();
                    showNoChanges();
                    return;
                }

                if (hint) hint.classList.add('hidden');
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
