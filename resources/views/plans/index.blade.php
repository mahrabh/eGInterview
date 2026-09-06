<x-dark-layout>
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Plans</h2>
            <p class="text-slate-500 mt-1.5 text-sm">Define pricing and usage limits for workspace users.</p>
        </div>
        <a href="{{ route('plans.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-colors shrink-0">
            <svg class="w-4 h-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add New Plan
        </a>
    </div>

    <div class="glass-panel rounded-2xl overflow-hidden relative">
        @if(session('success'))
            <div class="flash-message bg-emerald-50 text-emerald-700 px-5 py-3.5 border-b border-emerald-200 font-semibold text-sm flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[720px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Plan Name</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Price</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Limits <span class="lowercase text-[10px] normal-case">(I / AI)</span></th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($plans as $plan)
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-100 transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-900 text-sm">{{ $plan->name }}</h3>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-0.5">{{ $plan->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-slate-800 font-semibold text-sm">
                            @if($plan->price > 0)
                                ${{ number_format($plan->price, 2) }}
                            @else
                                <span class="text-slate-500">Free</span>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-100 border border-slate-200 text-slate-700 px-2 py-1 rounded text-xs font-semibold" title="Interviews">{{ $plan->interview_limit }}</span>
                                <span class="bg-slate-100 border border-slate-200 text-slate-700 px-2 py-1 rounded text-xs font-semibold" title="AI Generations">{{ $plan->ai_generation_limit }}</span>
                            </div>
                        </td>
                        <td class="px-5 sm:px-6 py-4">
                            @if($plan->is_active)
                                <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                            @else
                                <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full bg-slate-100 text-slate-500 border border-slate-200">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-right">
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('plans.edit', $plan) }}" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg border border-transparent hover:border-indigo-200 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-200 transition-all">
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
            <div class="px-5 sm:px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $plans->links() }}
            </div>
        @endif
    </div>
</x-dark-layout>
