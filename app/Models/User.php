<?php

namespace App\Models;

use App\Services\PlanQuotaService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'plan_id', 'expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Access ends at end of expires_at calendar day (inclusive).
     * Admins never expire. Null means no expiry.
     */
    public function isExpired(): bool
    {
        if ($this->isAdmin() || $this->expires_at === null) {
            return false;
        }

        return now()->greaterThan($this->expires_at->copy()->endOfDay());
    }

    public function hasExpiry(): bool
    {
        return ! $this->isAdmin() && $this->expires_at !== null;
    }

    public function expiryLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Never';
        }

        if ($this->expires_at === null) {
            return 'No expiry';
        }

        return $this->expires_at->format('M j, Y');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'user_id');
    }

    public function loanApplicants(): HasMany
    {
        return $this->hasMany(LoanApplicant::class, 'created_by');
    }

    public function billingHistories(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRecruiter(): bool
    {
        return $this->role === 'recruiter';
    }

    public function isAnalyst(): bool
    {
        return $this->role === 'analyst';
    }

    public function isBoth(): bool
    {
        return $this->role === 'both';
    }

    public function canAccessRecruitment(): bool
    {
        return $this->isAdmin() || $this->isRecruiter() || $this->isBoth();
    }

    public function canAccessLoans(): bool
    {
        return $this->isAdmin() || $this->isAnalyst() || $this->isBoth();
    }

    /**
     * Roles used for middleware checks (both expands to recruiter + analyst).
     *
     * @return list<string>
     */
    public function effectiveRoles(): array
    {
        return match ($this->role) {
            'admin' => ['admin'],
            'both' => ['both', 'recruiter', 'analyst'],
            'recruiter' => ['recruiter'],
            'analyst' => ['analyst'],
            default => array_filter([(string) $this->role]),
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'both' => 'Recruiter + Analyst',
            'analyst' => 'Analyst',
            'recruiter' => 'Recruiter',
            default => ucfirst((string) $this->role),
        };
    }

    /** @return list<string> */
    public static function assignableRoles(): array
    {
        return ['analyst', 'recruiter', 'both'];
    }

    /**
     * Resolve checkbox selections into a stored role value.
     *
     * @param  list<string>|null  $roles
     */
    public static function roleFromSelections(?array $roles): ?string
    {
        $roles = collect($roles ?? [])
            ->map(fn ($role) => (string) $role)
            ->filter(fn ($role) => in_array($role, ['recruiter', 'analyst'], true))
            ->unique()
            ->values();

        if ($roles->count() === 2) {
            return 'both';
        }

        if ($roles->count() === 1) {
            return $roles->first();
        }

        return null;
    }

    public function planUsageSnapshot(): array
    {
        return app(PlanQuotaService::class)->usageSnapshot($this);
    }

    public function moduleAccessLabel(): string
    {
        if ($this->isAdmin() || $this->isBoth()) {
            return 'Recruitment + Loan Applicants';
        }

        if ($this->isRecruiter()) {
            return 'Recruitment';
        }

        if ($this->isAnalyst()) {
            return 'Loan Applicants';
        }

        return 'None';
    }

    public function accountStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->planUsageSnapshot()['account_status'];
    }
}
