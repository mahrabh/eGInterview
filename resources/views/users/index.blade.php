<x-dark-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center w-full">
            <span>Users Management</span>
            <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 border border-transparent rounded-xl font-bold text-xs text-white uppercase tracking-widest transition-all shadow-[0_0_15px_rgba(79,70,229,0.3)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Recruiter
            </a>
        </div>
    </x-slot>

    <div class="glass-panel border border-slate-800 rounded-[2rem] overflow-hidden shadow-2xl relative">
        @if(session('success'))
            <div class="flash-message bg-emerald-500/10 text-emerald-400 px-6 py-4 border-b border-emerald-500/20 font-bold text-sm flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flash-message bg-rose-500/10 text-rose-400 px-6 py-4 border-b border-rose-500/20 font-bold text-sm flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800/50 bg-slate-900/50">
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Name</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Email</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Role</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Plan</th>
                        <th class="px-8 py-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-800/20 transition-colors group">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl {{ $user->isAdmin() ? 'bg-indigo-500/10 border border-indigo-500/20 text-indigo-400' : 'bg-slate-800 border border-slate-700 text-slate-300 group-hover:bg-indigo-500/10 group-hover:text-indigo-400 group-hover:border-indigo-500/20' }} flex items-center justify-center font-black text-lg shadow-inner transition-all">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <span class="font-bold text-white text-sm">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-sm text-slate-400 font-medium">{{ $user->email }}</td>
                        <td class="px-8 py-6">
                            <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-full {{ $user->isAdmin() ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 border border-slate-700 text-slate-400' }}">
                                {{ $user->isAdmin() ? 'Admin' : $user->roleLabel() }}
                            </span>
                        </td>
                        <td class="px-8 py-6">
                            @if($user->isAdmin())
                                <span class="text-xs text-slate-600 font-bold italic">N/A</span>
                            @elseif($user->plan)
                                <div class="flex flex-col items-start gap-1.5">
                                    <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        {{ $user->plan->name }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-bold">
                                        {{ $user->interviews_count }} / {{ $user->plan->interview_limit }} Used
                                    </span>
                                </div>
                            @else
                                <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-widest rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    No Plan
                                </span>
                            @endif
                        </td>
                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end items-center gap-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                @if($user->isAdmin())
                                    {{-- Admin: protected, no edit/delete from here --}}
                                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-widest italic px-3">Protected</span>
                                    <span class="w-8 h-8 flex items-center justify-center text-indigo-500/30 cursor-not-allowed" title="Admin is protected — use Account Settings">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    </span>
                                @else
                                    {{-- Recruiter: edit + delete --}}
                                    <a href="{{ route('users.edit', $user) }}" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 rounded-lg border border-transparent hover:border-indigo-500/20 transition-all" title="Edit recruiter">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete {{ addslashes($user->name) }} permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg border border-transparent hover:border-rose-500/20 transition-all" title="Delete recruiter">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="px-8 py-4 border-t border-slate-800 bg-slate-900/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-dark-layout>
