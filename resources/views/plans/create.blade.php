<x-dark-layout>
    <div class="mb-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Create Plan</h2>
            <p class="text-xs text-slate-500 mt-0.5 truncate">Define pricing and usage limits for workspace users.</p>
        </div>
        <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 transition-colors shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('plans.store') }}" id="create-plan-form">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <aside class="lg:col-span-4">
                <div class="glass-panel rounded-xl overflow-hidden h-full">
                    <div class="px-4 py-3 bg-gradient-to-br from-slate-50 via-indigo-50/50 to-cyan-50/40 border-b border-slate-200/80">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-slate-900">New workspace plan</h3>
                                <p class="text-[11px] text-slate-500">Pricing & usage limits</p>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span class="inline-flex px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-white border border-slate-200 text-slate-600">Billing</span>
                            <span class="inline-flex px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600">AI limits</span>
                        </div>
                    </div>

                    <div class="p-3.5 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Plan controls</p>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Monthly price & slug
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Interview usage limit
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            AI generation limit
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="w-4 h-4 rounded bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            Active for assignment
                        </div>
                    </div>
                </div>
            </aside>

            <section class="lg:col-span-8">
                <div class="glass-panel rounded-xl overflow-hidden flex flex-col">
                    <div class="px-4 py-2.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/40 flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Plan configuration</h3>
                            <p class="text-[11px] text-slate-500">Pricing, limits, and availability</p>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1">Plan name</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="e.g. Pro Plan">
                                <x-input-error :messages="$errors->get('name')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="slug" class="block text-xs font-semibold text-slate-700 mb-1">Slug identifier</label>
                                <input id="slug" type="text" name="slug" value="{{ old('slug') }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="pro-plan">
                                <x-input-error :messages="$errors->get('slug')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="price" class="block text-xs font-semibold text-slate-700 mb-1">Price (USD)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-sm font-semibold">$</span>
                                    <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', 0) }}" required
                                        class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 pl-7 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                </div>
                                <x-input-error :messages="$errors->get('price')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="interview_limit" class="block text-xs font-semibold text-slate-700 mb-1">Interview limit</label>
                                <input id="interview_limit" type="number" min="0" name="interview_limit" value="{{ old('interview_limit', 0) }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                <x-input-error :messages="$errors->get('interview_limit')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div>
                                <label for="ai_generation_limit" class="block text-xs font-semibold text-slate-700 mb-1">AI generation limit</label>
                                <input id="ai_generation_limit" type="number" min="0" name="ai_generation_limit" value="{{ old('ai_generation_limit', 0) }}" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                <x-input-error :messages="$errors->get('ai_generation_limit')" class="mt-1 text-rose-600 text-xs" />
                            </div>

                            <div class="md:col-span-2">
                                <label for="is_active" class="flex items-start gap-2.5 cursor-pointer group p-3 rounded-lg border border-slate-200 bg-slate-50 hover:border-slate-300 transition-colors">
                                    <input type="hidden" name="is_active" value="0">
                                    <input id="is_active" type="checkbox" name="is_active" value="1" class="mt-0.5 w-4 h-4 rounded border-slate-300 bg-white text-indigo-600 focus:ring-indigo-500/30" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span>
                                        <span class="block text-xs font-semibold text-slate-900 group-hover:text-indigo-600 transition-colors">Plan is active</span>
                                        <span class="block text-[11px] text-slate-500 mt-0.5">Workspace users can be assigned this plan.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-0 px-4 py-3 border-t border-slate-200 bg-white/95 backdrop-blur-sm flex items-center justify-end gap-3">
                        <a href="{{ route('plans.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors px-2 py-1.5">Cancel</a>
                        <button type="submit" id="create-plan-btn" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors min-w-[132px]">
                            <svg id="create-plan-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            <svg id="create-plan-spinner" class="w-4 h-4 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span id="create-plan-text">Create Plan</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('create-plan-form');
            if (!form) return;
            const btn = document.getElementById('create-plan-btn');
            const icon = document.getElementById('create-plan-icon');
            const spinner = document.getElementById('create-plan-spinner');
            const text = document.getElementById('create-plan-text');

            function resetBtn() {
                if (!btn) return;
                btn.disabled = false;
                btn.classList.remove('opacity-80', 'cursor-not-allowed', 'pointer-events-none');
                if (icon) icon.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (text) text.textContent = 'Create Plan';
            }

            form.addEventListener('submit', function () {
                if (icon) icon.classList.add('hidden');
                if (spinner) spinner.classList.remove('hidden');
                if (text) text.textContent = 'Creating...';
                if (btn) btn.classList.add('opacity-80', 'pointer-events-none');
                setTimeout(function () {
                    if (btn) { btn.disabled = true; btn.classList.add('cursor-not-allowed'); }
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
