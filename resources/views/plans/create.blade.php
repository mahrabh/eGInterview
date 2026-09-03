<x-dark-layout>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Create Plan</h2>
            <p class="text-slate-400 mt-1.5 text-sm">Define pricing and usage limits for workspace users.</p>
        </div>
        <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl text-sm font-semibold text-slate-300 transition-all shrink-0">
            ← Back to Plans
        </a>
    </div>

    <form method="POST" action="{{ route('plans.store') }}">
        @csrf

        <div class="glass-panel border border-slate-800 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800/70 bg-slate-900/40 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Plan configuration</h3>
                    <p class="text-sm text-slate-400 mt-0.5">Pricing, limits, and availability.</p>
                </div>
            </div>

            <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Plan name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                        placeholder="e.g. Pro Plan">
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-300 mb-2">Slug identifier</label>
                    <input id="slug" type="text" name="slug" value="{{ old('slug') }}" required
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 font-mono placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                        placeholder="pro-plan">
                    <x-input-error :messages="$errors->get('slug')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-slate-300 mb-2">Price (USD)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500 font-semibold">$</span>
                        <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', 0) }}" required
                            class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 pl-8 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                    </div>
                    <x-input-error :messages="$errors->get('price')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="interview_limit" class="block text-sm font-medium text-slate-300 mb-2">Interview limit</label>
                    <input id="interview_limit" type="number" min="0" name="interview_limit" value="{{ old('interview_limit', 0) }}" required
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                    <x-input-error :messages="$errors->get('interview_limit')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="ai_generation_limit" class="block text-sm font-medium text-slate-300 mb-2">AI generation limit</label>
                    <input id="ai_generation_limit" type="number" min="0" name="ai_generation_limit" value="{{ old('ai_generation_limit', 0) }}" required
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                    <x-input-error :messages="$errors->get('ai_generation_limit')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div class="md:col-span-2 pt-2">
                    <label for="is_active" class="flex items-start gap-3 cursor-pointer group p-4 rounded-xl border border-slate-800 bg-slate-900/40 hover:border-slate-700 transition-colors">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1" class="mt-0.5 w-5 h-5 rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500/30" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span>
                            <span class="block text-sm font-semibold text-white group-hover:text-indigo-300 transition-colors">Plan is active</span>
                            <span class="block text-sm text-slate-400 mt-0.5">Workspace users can be assigned this plan.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="px-6 md:px-8 py-5 bg-slate-900/50 border-t border-slate-800/70 flex items-center justify-end gap-4">
                <a href="{{ route('plans.index') }}" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors px-2 py-2">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-[0_0_20px_rgba(79,70,229,0.25)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Create Plan
                </button>
            </div>
        </div>
    </form>
</x-dark-layout>
