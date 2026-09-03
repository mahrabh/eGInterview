<x-dark-layout>
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Plans</h2>
            <p class="text-slate-400 mt-1.5 text-sm">Define pricing and usage limits for workspace users.</p>
        </div>
        <a href="{{ route('plans.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600/90 hover:bg-indigo-500 text-white text-sm font-semibold border border-indigo-400/20 transition-colors shrink-0">
            <svg class="w-4 h-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add New Plan
        </a>
    </div>

    <div class="glass-panel border border-slate-800 rounded-2xl overflow-hidden relative">
        @if(session('success'))
            <div class="flash-message bg-emerald-500/10 text-emerald-400 px-6 py-4 border-b border-emerald-500/20 font-bold text-sm flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800/50 bg-slate-900/50">
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Plan Name</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Price</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Limits <span class="lowercase text-[10px] normal-case">(I / AI)</span></th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($plans as $plan)
                    <tr class="hover:bg-slate-800/20 transition-colors group">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-5">
                                <div class="w-10 h-10 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 shadow-inner group-hover:bg-indigo-500/10 group-hover:border-indigo-500/20 transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-white text-sm">{{ $plan->name }}</h3>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">{{ $plan->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-slate-200 font-bold text-sm">
                            @if($plan->price > 0)
                                ${{ number_format($plan->price, 2) }}
                            @else
                                <span class="text-slate-500">Free</span>
                            @endif
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-800 border border-slate-700 text-slate-300 px-2 py-1 rounded text-xs font-bold" title="Interviews">{{ $plan->interview_limit }}</span>
                                <span class="bg-slate-800 border border-slate-700 text-slate-300 px-2 py-1 rounded text-xs font-bold" title="AI Generations">{{ $plan->ai_generation_limit }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            @if($plan->is_active)
                                <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                            @else
                                <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-full bg-slate-800 text-slate-400 border border-slate-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end space-x-3 opacity-80 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('plans.edit', $plan) }}" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-lg border border-transparent hover:border-indigo-500/20 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg border border-transparent hover:border-rose-500/20 transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($plans->hasPages())
            <div class="px-8 py-4 border-t border-slate-800 bg-slate-900/50">
                {{ $plans->links() }}
            </div>
        @endif
    </div>
</x-dark-layout>
