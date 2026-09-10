<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingHistory extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'plan_id',
        'plan_name',
        'plan_module',
        'recruitment_limit',
        'loan_limit',
        'amount',
        'currency',
        'gateway',
        'description',
        'status',
        'period_start',
        'period_end',
        'paid_at',
        'voided_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recruitment_limit' => 'integer',
            'loan_limit' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public const STATUSES = ['pending', 'paid', 'failed', 'void'];

    public const GATEWAYS = ['Bank Transfer', 'PayPal', 'Stripe', 'Cash', 'Other'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'void' => 'Void',
            default => ucfirst((string) $this->status),
        };
    }

    public function periodLabel(): string
    {
        if ($this->period_start && $this->period_end) {
            return $this->period_start->format('M j, Y').' – '.$this->period_end->format('M j, Y');
        }

        if ($this->period_end) {
            return 'Until '.$this->period_end->format('M j, Y');
        }

        return '—';
    }

    public function voidPurgeLabel(): ?string
    {
        if ($this->status !== 'void' || $this->voided_at === null) {
            return null;
        }

        $purgeAt = $this->voided_at->copy()->addDays(\App\Services\BillingService::VOID_RETENTION_DAYS);
        $daysLeft = (int) now()->startOfDay()->diffInDays($purgeAt->copy()->startOfDay(), false);

        if ($daysLeft < 0) {
            return 'Pending auto-delete';
        }

        if ($daysLeft === 0) {
            return 'Auto-deletes today';
        }

        return 'Auto-deletes in '.$daysLeft.' day'.($daysLeft === 1 ? '' : 's');
    }
}
