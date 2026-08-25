<x-dark-layout>
    <div class="flex flex-col items-center justify-center min-h-[calc(100vh-200px)] px-4">
        <div class="w-full max-w-2xl">
            {{-- Page Header --}}
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10 text-center md:text-left">
                <div>
                    <h2 class="text-4xl font-black text-white tracking-tight">Create New Plan</h2>
                    <p class="text-slate-400 mt-2 text-sm font-medium">Define pricing and limits for your recruiters.</p>
                </div>
                <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl font-bold text-xs text-slate-300 uppercase tracking-widest transition-all shrink-0">
                    ← Back to Plans
                </a>
            </div>

            <form method="POST" action="{{ route('plans.store') }}" class="space-y-6">
                @csrf

                <div class="glass-panel border border-slate-800 rounded-[2.5rem] shadow-2xl overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-800/50 bg-slate-900/30 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20 shadow-inner">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <h3 class="text-base font-black text-white uppercase tracking-widest">Plan Configuration</h3>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">Plan Name</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-2xl px-5 py-4 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all font-medium"
                                placeholder="e.g. Pro Plan">
                            <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm font-bold" />
                        </div>

                        <div>
                            <label for="slug" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">Slug Identifier</label>
                            <input id="slug" type="text" name="slug" value="{{ old('slug') }}" required
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-2xl px-5 py-4 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all font-mono text-sm"
                                placeholder="pro-plan">
                            <x-input-error :messages="$errors->get('slug')" class="mt-2 text-rose-400 text-sm font-bold" />
                        </div>

                        <div>
                            <label for="price" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">Price (USD)</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-5 text-slate-500 font-bold group-focus-within:text-indigo-400 transition-colors">$</span>
                                <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', 0) }}" required
                                    class="w-full bg-slate-950/50 border border-slate-700 rounded-2xl px-5 py-4 pl-10 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all font-black text-lg">
                            </div>
                            <x-input-error :messages="$errors->get('price')" class="mt-2 text-rose-400 text-sm font-bold" />
                        </div>

                        <div>
                            <label for="interview_limit" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">Interview Limit</label>
                            <input id="interview_limit" type="number" min="0" name="interview_limit" value="{{ old('interview_limit', 0) }}" required
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-2xl px-5 py-4 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all font-black text-xl">
                            <x-input-error :messages="$errors->get('interview_limit')" class="mt-2 text-rose-400 text-sm font-bold" />
                        </div>

                        <div>
                            <label for="ai_generation_limit" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">AI Generation Limit</label>
                            <input id="ai_generation_limit" type="number" min="0" name="ai_generation_limit" value="{{ old('ai_generation_limit', 0) }}" required
                                class="w-full bg-slate-950/50 border border-slate-700 rounded-2xl px-5 py-4 text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all font-black text-xl">
                            <x-input-error :messages="$errors->get('ai_generation_limit')" class="mt-2 text-rose-400 text-sm font-bold" />
                        </div>

                        <div class="md:col-span-2 pt-4">
                            <label for="is_active" class="flex items-center gap-4 cursor-pointer group">
                                <input type="hidden" name="is_active" value="0">
                                <div class="relative flex items-center justify-center">
                                    <input id="is_active" type="checkbox" name="is_active" value="1" class="peer appearance-none w-7 h-7 border-2 border-slate-700 rounded-xl bg-slate-900/50 checked:bg-indigo-500 checked:border-indigo-500 transition-all cursor-pointer" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <svg class="absolute w-4 h-4 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div>
                                    <span class="block text-sm font-black text-white group-hover:text-indigo-400 transition-colors">Plan is Active</span>
                                    <span class="block text-[11px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">Recruiters can use this plan</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="px-8 py-6 bg-slate-900/50 border-t border-slate-800/50 flex flex-col md:flex-row items-center justify-end gap-4">
                        <a href="{{ route('plans.index') }}" class="text-sm font-bold text-slate-400 hover:text-white transition-colors px-6 py-4">Cancel</a>
                        <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-10 py-4 rounded-2xl font-black transition-all shadow-[0_0_30px_rgba(79,70,229,0.3)] hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Create Plan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-dark-layout>
