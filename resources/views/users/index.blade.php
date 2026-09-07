<x-dark-layout>
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Users</h2>
            <p class="text-slate-500 mt-1.5 text-sm">Manage roles, plan assignment, module access, and monthly usage.</p>
        </div>
        <a href="{{ route('users.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition-colors shrink-0">
            <svg class="w-4 h-4 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add User
        </a>
    </div>

    <div class="glass-panel rounded-2xl overflow-hidden relative">
        @if(session('success'))
            <div class="flash-message bg-emerald-50 text-emerald-700 px-5 py-3.5 border-b border-emerald-200 font-semibold text-sm flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="flash-message bg-rose-50 text-rose-700 px-5 py-3.5 border-b border-rose-200 font-semibold text-sm flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1200px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">User</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Plan</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Module Access</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Monthly Usage</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Remaining</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Expiry</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 sm:px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $user)
                        @php
                            $snapshot = $user->planUsageSnapshot();
                            $recruitUsed = (int) ($user->recruitment_month_count ?? $snapshot['recruitment_used']);
                            $loanUsed = (int) ($user->loan_month_count ?? $snapshot['loan_used']);
                        @endphp
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="px-5 sm:px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl {{ $user->isAdmin() ? 'bg-indigo-50 border border-indigo-100 text-indigo-700' : 'bg-slate-100 border border-slate-200 text-slate-700 group-hover:bg-indigo-50 group-hover:text-indigo-700 group-hover:border-indigo-100' }} flex items-center justify-center font-bold text-sm transition-all">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 text-sm truncate">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 sm:px-6 py-4">
                            <span class="px-3 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full {{ $user->isAdmin() ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 border border-slate-200 text-slate-600' }}">
                                {{ $user->roleLabel() }}
                            </span>
                        </td>
                        <td class="px-5 sm:px-6 py-4">
                            @if($user->isAdmin())
                                <span class="text-xs text-slate-400 font-semibold italic">Not required</span>
                            @elseif($user->plan)
                                <span class="px-2.5 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ $user->plan->name }}
                                </span>
                            @else
                                <span class="px-2.5 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                    No Plan
                                </span>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-sm text-slate-700 font-medium">
                            {{ $user->moduleAccessLabel() }}
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-xs text-slate-600">
                            @if($user->isAdmin())
                                <span class="text-slate-400 italic">Unlimited</span>
                            @else
                                <div class="space-y-0.5">
                                    @if($user->canAccessRecruitment())
                                        <p>Recruitment: <span class="font-semibold text-slate-800 tabular-nums">{{ $recruitUsed }}</span>@if($snapshot['recruitment_limit'] !== null) / {{ $snapshot['recruitment_limit'] }}@endif</p>
                                    @endif
                                    @if($user->canAccessLoans())
                                        <p>Loans: <span class="font-semibold text-slate-800 tabular-nums">{{ $loanUsed }}</span>@if($snapshot['loan_limit'] !== null) / {{ $snapshot['loan_limit'] }}@endif</p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-xs text-slate-600">
                            @if($user->isAdmin())
                                <span class="font-semibold text-emerald-700">∞</span>
                            @else
                                <div class="space-y-0.5">
                                    @if($user->canAccessRecruitment())
                                        <p>R: <span class="font-semibold tabular-nums">{{ $snapshot['recruitment_remaining'] ?? '—' }}</span></p>
                                    @endif
                                    @if($user->canAccessLoans())
                                        <p>L: <span class="font-semibold tabular-nums">{{ $snapshot['loan_remaining'] ?? '—' }}</span></p>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-xs text-slate-600">
                            @if($user->isExpired())
                                <span class="font-semibold text-rose-700">{{ $user->expiryLabel() }}</span>
                            @elseif($user->hasExpiry())
                                <span class="font-semibold text-slate-800">{{ $user->expiryLabel() }}</span>
                            @else
                                <span class="text-slate-400 italic">{{ $user->expiryLabel() }}</span>
                            @endif
                        </td>
                        <td class="px-5 sm:px-6 py-4">
                            @php
                                $status = $user->accountStatusLabel();
                                $statusClass = match ($status) {
                                    'Unrestricted', 'Active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'Plan inactive' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'Expired' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-slate-100 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="px-2.5 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full border {{ $statusClass }}">
                                {{ $status }}
                            </span>
                        </td>
                        <td class="px-5 sm:px-6 py-4 text-right">
                            <div class="flex justify-end items-center gap-2">
                                @if($user->isAdmin())
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide italic px-3">Protected</span>
                                @else
                                    <a href="{{ route('users.edit', $user) }}" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg border border-transparent hover:border-indigo-200 transition-all" title="Edit user">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete {{ addslashes($user->name) }} permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-200 transition-all" title="Delete user">
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
            <div class="px-5 sm:px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-dark-layout>
