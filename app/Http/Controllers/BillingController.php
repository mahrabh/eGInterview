<?php

namespace App\Http\Controllers;

use App\Models\BillingHistory;
use App\Models\User;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    public function index(Request $request): View
    {
        $auth = $request->user();
        $query = BillingHistory::query()->with(['user', 'plan'])->latest();

        if (! $auth->isAdmin()) {
            $query->where('user_id', $auth->id);
        } else {
            if ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->user_id);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
        }

        $payments = $query->paginate(15)->withQueryString();

        $statsQuery = BillingHistory::query();
        if (! $auth->isAdmin()) {
            $statsQuery->where('user_id', $auth->id);
        } else {
            if ($request->filled('user_id')) {
                $statsQuery->where('user_id', (int) $request->user_id);
            }
            if ($request->filled('status')) {
                $statsQuery->where('status', $request->status);
            }
        }

        $totalPaid = (clone $statsQuery)->where('status', 'paid')->sum('amount');
        $paidCount = (clone $statsQuery)->where('status', 'paid')->count();
        $pendingCount = (clone $statsQuery)->where('status', 'pending')->count();
        $lastPaid = (clone $statsQuery)->where('status', 'paid')->latest('paid_at')->first();

        $users = $auth->isAdmin()
            ? User::query()->where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $needsRecreate = $auth->isAdmin()
            ? $this->billing->usersNeedingInvoiceRecreation()
            : collect();

        return view('billing.index', compact(
            'payments',
            'users',
            'totalPaid',
            'paidCount',
            'pendingCount',
            'lastPaid',
            'needsRecreate',
        ));
    }

    public function update(Request $request, BillingHistory $billing): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($billing->status === 'void') {
            return back()->with(
                'error',
                'Voided invoices cannot be edited. Use Restore first, then update payment details.'
            );
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'gateway' => ['nullable', 'string', Rule::in(BillingHistory::GATEWAYS)],
            'status' => ['required', Rule::in(['pending', 'paid', 'failed'])],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'amount' => round((float) $validated['amount'], 2),
            'gateway' => $validated['gateway'] ?? null,
            'status' => $validated['status'],
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'voided_at' => null,
        ];

        if ($validated['status'] === 'paid' && $billing->status !== 'paid') {
            $payload['paid_at'] = now();
        } elseif ($validated['status'] !== 'paid') {
            $payload['paid_at'] = null;
        }

        $billing->fill($payload);

        if (! $billing->isDirty()) {
            return back()->with('info', 'No changes to save.');
        }

        $billing->save();

        return redirect()->route('billing.index')->with('success', 'Billing record updated.');
    }

    public function void(Request $request, BillingHistory $billing): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($billing->status === 'void') {
            return back()->with('info', 'Invoice is already voided.');
        }

        $this->billing->voidInvoice($billing);

        return redirect()->route('billing.index')->with(
            'success',
            "Invoice {$billing->invoice_number} voided. Restore within ".\App\Services\BillingService::VOID_RETENTION_DAYS." days or it will be deleted automatically."
        );
    }

    public function restore(Request $request, BillingHistory $billing): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($billing->status !== 'void') {
            return back()->with('info', 'Only voided invoices can be restored.');
        }

        $this->billing->restoreVoidedInvoice($billing);

        return redirect()->route('billing.index')->with(
            'success',
            "Invoice {$billing->invoice_number} restored as pending."
        );
    }

    public function recreate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->isAdmin(), 422, 'Cannot recreate billing for the admin account.');

        $invoice = $this->billing->recreateForAssignedPlan($user, $request->user());

        if ($invoice === null) {
            return back()->with(
                'info',
                $user->plan_id
                    ? 'Nothing to recreate. Restore a voided invoice, or this user already has an active invoice.'
                    : 'This user has no plan assigned.'
            );
        }

        return redirect()->route('billing.index')->with(
            'success',
            "Invoice {$invoice->invoice_number} recreated for {$user->name}."
        );
    }

    public function download(Request $request, BillingHistory $billing): Response
    {
        $auth = $request->user();
        abort_unless($auth->isAdmin() || (int) $billing->user_id === (int) $auth->id, 403);

        $billing->load('user');

        $pdf = Pdf::loadView('billing.invoice-pdf', [
            'payment' => $billing,
            'invoiceNo' => $billing->invoice_number,
            'company' => [
                'name' => 'egMeet AI',
                'email' => 'support@egeneration.co',
            ],
        ])->setPaper('a4');

        return $pdf->download($billing->invoice_number.'.pdf');
    }
}
