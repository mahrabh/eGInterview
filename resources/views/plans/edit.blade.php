<x-dark-layout>
    <div class="max-w-4xl mx-auto mb-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Edit Plan</h2>
            <p class="text-xs text-slate-500 mt-0.5 truncate">Update limits for <span class="text-indigo-600 font-medium">{{ $plan->name }}</span>.</p>
        </div>
        <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back
        </a>
    </div>

    @if(session('info'))
        <div class="max-w-4xl mx-auto mb-3 flash-message bg-amber-50 text-amber-800 px-4 py-2.5 rounded-lg border border-amber-200 text-xs font-semibold">
            {{ session('info') }}
        </div>
    @endif

    <form method="POST" action="{{ route('plans.update', $plan) }}" id="edit-plan-form">
        @csrf
        @method('PUT')

        <div class="max-w-4xl mx-auto">
            <div class="glass-panel rounded-xl overflow-hidden flex flex-col">
                <div class="px-4 py-2.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 truncate">{{ $plan->name }}</h3>
                        <p class="text-[11px] text-slate-500 truncate">{{ $plan->moduleLabel() }} · {{ $plan->slug }}</p>
                    </div>
                </div>

                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Plan name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $plan->name) }}" required autofocus
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <x-input-error :messages="$errors->get('name')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <div>
                            <label for="slug" class="block text-xs font-semibold text-slate-700 mb-1">Slug identifier</label>
                            <input id="slug" type="text" value="{{ old('slug', $plan->slug) }}" readonly
                                class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-600 font-mono cursor-default focus:outline-none"
                                tabindex="-1">
                            <p class="text-[11px] text-slate-500 mt-1">System-managed and kept stable when the plan is edited.</p>
                        </div>

                        <div>
                            <label for="module_type" class="block text-xs font-semibold text-slate-700 mb-1">Module type</label>
                            <select id="module_type" name="module_type" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                @foreach($moduleTypes as $type)
                                    <option value="{{ $type->value }}" {{ old('module_type', $plan->moduleTypeEnum()->value) === $type->value ? 'selected' : '' }}>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('module_type')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <div>
                            <label for="price" class="block text-xs font-semibold text-slate-700 mb-1">Price (USD)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm font-semibold">$</span>
                                <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price) }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 pl-7 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            </div>
                            <x-input-error :messages="$errors->get('price')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <div id="recruitment-limit-field">
                            <label for="recruitment_interview_limit" class="block text-xs font-semibold text-slate-700 mb-1">Recruitment monthly limit</label>
                            <input id="recruitment_interview_limit" type="number" min="0" name="recruitment_interview_limit" value="{{ old('recruitment_interview_limit', $plan->effectiveRecruitmentLimit()) }}" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <x-input-error :messages="$errors->get('recruitment_interview_limit')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <div id="loan-limit-field">
                            <label for="loan_interview_limit" class="block text-xs font-semibold text-slate-700 mb-1">Loan Applicants monthly limit</label>
                            <input id="loan_interview_limit" type="number" min="0" name="loan_interview_limit" value="{{ old('loan_interview_limit', $plan->effectiveLoanLimit()) }}" required
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <x-input-error :messages="$errors->get('loan_interview_limit')" class="mt-1 text-rose-600 text-xs" />
                        </div>

                        <input type="hidden" name="ai_generation_limit" value="{{ old('ai_generation_limit', $plan->ai_generation_limit) }}">

                        <div class="md:col-span-2">
                            <label for="is_active" class="flex items-start gap-2.5 cursor-pointer group p-3 rounded-lg border border-slate-200 bg-slate-50 hover:border-slate-300 transition-colors">
                                <input type="hidden" name="is_active" value="0">
                                <input id="is_active" type="checkbox" name="is_active" value="1" class="mt-0.5 w-4 h-4 rounded border-slate-300 bg-white text-indigo-600 focus:ring-indigo-500/30" {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
                                <span>
                                    <span class="block text-xs font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors">Plan is active</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5">Workspace users can be assigned this plan.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 px-4 py-3 border-t border-slate-200 bg-white/95 backdrop-blur-sm flex flex-col gap-2">
                    <p id="edit-plan-hint" class="hidden text-xs font-semibold text-amber-700 text-right">No changes to save.</p>
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('plans.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors px-2 py-1.5">Cancel</a>
                        <button type="submit" id="edit-plan-btn" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors min-w-[132px]">
                            <svg id="edit-plan-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <svg id="edit-plan-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span id="edit-plan-text">Update Plan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('edit-plan-form');
            if (!form) return;
            const btn = document.getElementById('edit-plan-btn');
            const icon = document.getElementById('edit-plan-icon');
            const spinner = document.getElementById('edit-plan-spinner');
            const text = document.getElementById('edit-plan-text');
            const hint = document.getElementById('edit-plan-hint');
            const moduleSelect = document.getElementById('module_type');
            const recruitField = document.getElementById('recruitment-limit-field');
            const loanField = document.getElementById('loan-limit-field');

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

            function syncModuleFields() {
                const value = moduleSelect ? moduleSelect.value : 'recruitment';
                if (recruitField) recruitField.classList.toggle('hidden', value === 'loan_applicants');
                if (loanField) loanField.classList.toggle('hidden', value === 'recruitment');
            }

            if (moduleSelect) {
                moduleSelect.addEventListener('change', syncModuleFields);
                syncModuleFields();
            }

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
                if (text) text.textContent = 'Update Plan';
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
                    if (btn) { btn.disabled = true; btn.classList.add('cursor-not-allowed'); }
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
