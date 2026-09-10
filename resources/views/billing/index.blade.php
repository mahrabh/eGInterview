<x-dark-layout>
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Billing History</h2>
            <p class="text-slate-500 mt-1.5 text-sm">
                @if(Auth::user()->isAdmin())
                    Invoices are created when a plan is assigned. Void instead of delete; restore or recreate if needed.
                @else
                    View your invoices and download receipts for plan payments.
                @endif
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="flash-message mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl border border-emerald-200 text-sm font-semibold">{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="flash-message mb-4 bg-amber-50 text-amber-800 px-4 py-3 rounded-xl border border-amber-200 text-sm font-semibold">{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="flash-message mb-4 bg-rose-50 text-rose-700 px-4 py-3 rounded-xl border border-rose-200 text-sm font-semibold">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
        <div class="glass-panel rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total Paid</p>
            <p class="text-xl font-black text-slate-900 mt-1 tabular-nums">${{ number_format((float) $totalPaid, 2) }}</p>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Paid Invoices</p>
            <p class="text-xl font-black text-slate-900 mt-1 tabular-nums">{{ $paidCount }}</p>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Pending</p>
            <p class="text-xl font-black text-amber-700 mt-1 tabular-nums">{{ $pendingCount }}</p>
        </div>
        <div class="glass-panel rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Last Payment</p>
            <p class="text-sm font-bold text-slate-900 mt-1">{{ $lastPaid?->paid_at?->format('M j, Y') ?? '—' }}</p>
            <p class="text-[11px] text-slate-500 mt-0.5">
                @if($lastPaid)
                    ${{ number_format((float) $lastPaid->amount, 2) }} · {{ $lastPaid->gateway ?? '—' }}
                @else
                    No paid invoices yet
                @endif
            </p>
        </div>
    </div>

    @if(Auth::user()->isAdmin() && isset($needsRecreate) && $needsRecreate->isNotEmpty())
        <div class="glass-panel rounded-xl p-4 mb-4 border border-amber-200 bg-amber-50/60">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                <div>
                    <p class="text-sm font-bold text-amber-900">Missing invoices</p>
                    <p class="text-[11px] text-amber-800/80 mt-0.5">These users have a plan but no pending, paid, or void invoice to restore. Recreate a new invoice for them.</p>
                </div>
            </div>
            <div class="space-y-2">
                @foreach($needsRecreate as $missingUser)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg bg-white border border-amber-100 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $missingUser->name }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ $missingUser->email }} · {{ $missingUser->plan?->name ?? 'Plan' }}</p>
                        </div>
                        <form method="POST" action="{{ route('billing.recreate', $missingUser) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-colors">
                                Recreate invoice
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(Auth::user()->isAdmin())
        <form method="GET" action="{{ route('billing.index') }}" class="glass-panel rounded-xl p-3 mb-4 flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="flex-1">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">User</label>
                <select name="user_id" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">All users</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-48">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\BillingHistory::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Filter</button>
            <a href="{{ route('billing.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-600 text-center">Reset</a>
        </form>
    @endif

    <div class="glass-panel rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[980px]">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Invoice</th>
                        @if(Auth::user()->isAdmin())
                            <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">User</th>
                        @endif
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Plan</th>
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Amount</th>
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Period</th>
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Date</th>
                        <th class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($payments as $payment)
                        @php
                            $statusClass = match ($payment->status) {
                                'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
                                'void' => 'bg-slate-100 text-slate-600 border-slate-200',
                                default => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $payment->invoice_number }}</p>
                                <p class="text-[11px] text-slate-500 truncate max-w-[220px]">{{ $payment->description }}</p>
                            </td>
                            @if(Auth::user()->isAdmin())
                                <td class="px-5 py-4">
                                    <p class="text-sm font-semibold text-slate-800">{{ $payment->user?->name ?? '—' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ $payment->user?->email }}</p>
                                </td>
                            @endif
                            <td class="px-5 py-4 text-sm text-slate-700">
                                <p class="font-medium">{{ $payment->plan_name ?? '—' }}</p>
                                <p class="text-[11px] text-slate-500">{{ $payment->plan_module }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-bold text-slate-900 tabular-nums">${{ number_format((float) $payment->amount, 2) }}</p>
                                <p class="text-[11px] text-slate-500">{{ $payment->gateway ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $payment->periodLabel() }}</td>
                            <td class="px-5 py-4">
                                <span class="px-2.5 py-1 inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full border {{ $statusClass }}">
                                    {{ $payment->statusLabel() }}
                                </span>
                                @if($payment->status === 'void' && $payment->voidPurgeLabel())
                                    <p class="text-[10px] text-slate-400 mt-1">{{ $payment->voidPurgeLabel() }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $payment->created_at->format('M j, Y') }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end items-center gap-1.5">
                                    <a href="{{ route('billing.invoice.download', $payment) }}" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg border border-transparent hover:border-indigo-200 transition-all" title="Download invoice">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    </a>
                                    @if(Auth::user()->isAdmin())
                                        @if($payment->status !== 'void')
                                            <button type="button"
                                                class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg border border-transparent hover:border-indigo-200 transition-all"
                                                title="Edit billing"
                                                data-edit-billing
                                                data-update-url="{{ route('billing.update', $payment) }}"
                                                data-amount="{{ (float) $payment->amount }}"
                                                data-gateway="{{ $payment->gateway }}"
                                                data-status="{{ $payment->status }}"
                                                data-period-start="{{ optional($payment->period_start)->format('Y-m-d') }}"
                                                data-period-end="{{ optional($payment->period_end)->format('Y-m-d') }}"
                                                data-notes="{{ $payment->notes }}"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                        @endif
                                        @if($payment->status === 'void')
                                            <form action="{{ route('billing.restore', $payment) }}" method="POST" class="inline" onsubmit="return confirm('Restore {{ $payment->invoice_number }} as pending?');">
                                                @csrf
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg border border-transparent hover:border-emerald-200 transition-all" title="Restore invoice">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('billing.void', $payment) }}" method="POST" class="inline" onsubmit="return confirm('Void invoice {{ $payment->invoice_number }}? It can be restored later.');">
                                                @csrf
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-200 transition-all" title="Void invoice">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="px-5 py-12 text-center text-sm text-slate-500">
                                No billing records yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
            <div class="px-5 py-4 border-t border-slate-200 bg-slate-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    @if(Auth::user()->isAdmin())
        {{-- Edit modal --}}
        <div id="edit-billing-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="edit-billing-title">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/50 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="min-w-0">
                            <h3 id="edit-billing-title" class="text-sm font-bold text-slate-900">Update payment</h3>
                            <p class="text-[11px] text-slate-500">Status, amount, gateway, and billing period</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeEditBilling()" class="w-8 h-8 rounded-lg text-slate-500 hover:bg-white hover:text-slate-800 border border-transparent hover:border-slate-200 transition-colors" aria-label="Close">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form id="edit-billing-form" method="POST" class="flex flex-col">
                    @csrf
                    @method('PUT')
                    <div class="p-5 space-y-3.5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="edit-amount" class="block text-xs font-semibold text-slate-700 mb-1">Amount (USD)</label>
                                <input type="number" step="0.01" min="0" name="amount" id="edit-amount" required
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            </div>
                            <div>
                                <label for="edit-status" class="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                                <select name="status" id="edit-status"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                    @foreach(['pending', 'paid', 'failed'] as $status)
                                        <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="edit-gateway" class="block text-xs font-semibold text-slate-700 mb-1">Gateway</label>
                            <select name="gateway" id="edit-gateway"
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                @foreach(\App\Models\BillingHistory::GATEWAYS as $gateway)
                                    <option value="{{ $gateway }}">{{ $gateway }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="edit-period-start" class="block text-xs font-semibold text-slate-700 mb-1">Period start</label>
                                <input type="date" name="period_start" id="edit-period-start"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            </div>
                            <div>
                                <label for="edit-period-end" class="block text-xs font-semibold text-slate-700 mb-1">Period end</label>
                                <input type="date" name="period_end" id="edit-period-end"
                                    class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            </div>
                        </div>
                        <div>
                            <label for="edit-notes" class="block text-xs font-semibold text-slate-700 mb-1">Notes <span class="font-normal text-slate-400">(optional)</span></label>
                            <textarea name="notes" id="edit-notes" rows="2" placeholder="Internal note for this payment"
                                class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/90 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeEditBilling()" class="px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function closeEditBilling() {
                const modal = document.getElementById('edit-billing-modal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            function openEditBilling(data) {
                const form = document.getElementById('edit-billing-form');
                form.action = data.update_url;
                document.getElementById('edit-amount').value = data.amount;
                document.getElementById('edit-status').value = data.status;
                document.getElementById('edit-gateway').value = data.gateway || 'Bank Transfer';
                document.getElementById('edit-period-start').value = data.period_start || '';
                document.getElementById('edit-period-end').value = data.period_end || '';
                document.getElementById('edit-notes').value = data.notes || '';
                const modal = document.getElementById('edit-billing-modal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
            document.querySelectorAll('[data-edit-billing]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openEditBilling({
                        update_url: btn.getAttribute('data-update-url'),
                        amount: btn.getAttribute('data-amount'),
                        gateway: btn.getAttribute('data-gateway'),
                        status: btn.getAttribute('data-status'),
                        period_start: btn.getAttribute('data-period-start'),
                        period_end: btn.getAttribute('data-period-end'),
                        notes: btn.getAttribute('data-notes'),
                    });
                });
            });
            document.getElementById('edit-billing-modal')?.addEventListener('click', function (e) {
                if (e.target === this) closeEditBilling();
            });
        </script>
    @endif
</x-dark-layout>
