<?php

namespace App\Models;

use App\Enums\BankOpeningStage;
use App\Support\BankOpening\AccountProductCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankOpeningApplication extends Model
{
    use HasUuids;

    /** @var int|null Request-local memo for panel progress (avoids repeat catalog + doc scans). */
    private ?int $memoRequiredDocumentsTotal = null;

    /** @var int|null */
    private ?int $memoRequiredDocumentsUploaded = null;

    /** @var int|null */
    private ?int $memoDocumentFieldsTotal = null;

    /** @var int|null */
    private ?int $memoDocumentFieldsUploaded = null;

    protected $fillable = [
        'bank_opening_applicant_id',
        'account_type',
        'bank_code',
        'catalog_version',
        'account_type_name_snapshot',
        'question_profile',
        'interview_follow_ups_used',
        'interview_question_index',
        'account_type_confirmed',
        'interview_language',
        'interview_session_id',
        'stage',
        'documents_required',
        'documents_uploaded',
        'public_token_hash',
        'public_token_expiry',
        'information_submitted_at',
        'interview_completed_at',
        'summary_confirmed_at',
        'submitted_at',
        'documents_submitted_at',
        'notes',
        'meta',
        'structured_answers',
        'confirmed_summary',
        'transcript',
        'transcript_text',
    ];

    protected function casts(): array
    {
        return [
            'stage' => BankOpeningStage::class,
            'account_type_confirmed' => 'boolean',
            'public_token_expiry' => 'datetime',
            'information_submitted_at' => 'datetime',
            'interview_completed_at' => 'datetime',
            'summary_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'documents_submitted_at' => 'datetime',
            'meta' => 'array',
            'structured_answers' => 'array',
            'confirmed_summary' => 'array',
            'documents_required' => 'integer',
            'documents_uploaded' => 'integer',
            'interview_follow_ups_used' => 'integer',
            'interview_question_index' => 'integer',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(BankOpeningApplicant::class, 'bank_opening_applicant_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BankOpeningApplicationEvent::class);
    }

    public function interviewTurns(): HasMany
    {
        return $this->hasMany(BankOpeningInterviewTurn::class)->orderBy('sequence');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BankOpeningDocument::class);
    }

    public function activeDocuments(): HasMany
    {
        return $this->hasMany(BankOpeningDocument::class)->active()->latest();
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   description: string|null,
     *   required: bool,
     *   max_files: int,
     *   conditional: bool
     * }>
     */
    public function documentRequirementGroups(): array
    {
        return \App\Support\BankOpening\DocumentRequirementCatalog::groupsForAccountType(
            $this->account_type,
            $this->meta
        );
    }

    public function accountTypeLabel(): ?string
    {
        if (filled($this->account_type_name_snapshot)) {
            return $this->account_type_name_snapshot;
        }

        return AccountProductCatalog::labelFor($this->account_type);
    }

    public function isInterviewFinished(): bool
    {
        return $this->interview_completed_at !== null
            || in_array($this->stage, [
                BankOpeningStage::InterviewCompleted,
                BankOpeningStage::DocumentsPending,
                BankOpeningStage::Submitted,
                BankOpeningStage::UnderReview,
                BankOpeningStage::ResubmissionRequired,
                BankOpeningStage::Completed,
            ], true);
    }

    public function isApplicationSubmitted(): bool
    {
        return $this->submitted_at !== null
            || in_array($this->stage, [
                BankOpeningStage::Submitted,
                BankOpeningStage::UnderReview,
                BankOpeningStage::Completed,
            ], true);
    }

    public function documentProgressLabel(): string
    {
        $uploaded = $this->documentFieldsUploadedCount();
        $total = $this->documentFieldsTotal();

        if ($total === 0) {
            return $uploaded > 0 ? (string) $uploaded : '—';
        }

        return "{$uploaded}/{$total}";
    }

    public function documentProgressPercent(): int
    {
        $total = $this->documentFieldsTotal();
        if ($total === 0) {
            return 0;
        }

        return (int) min(100, round(($this->documentFieldsUploadedCount() / $total) * 100));
    }

    /** All document slots for this account type (required + optional). */
    public function documentFieldsTotal(): int
    {
        if ($this->memoDocumentFieldsTotal !== null) {
            return $this->memoDocumentFieldsTotal;
        }

        if (! filled($this->account_type)) {
            return $this->memoDocumentFieldsTotal = 0;
        }

        return $this->memoDocumentFieldsTotal = count(
            \App\Support\BankOpening\DocumentRequirementCatalog::allKeysFor(
                $this->account_type,
                $this->meta
            )
        );
    }

    public function documentFieldsUploadedCount(): int
    {
        if ($this->memoDocumentFieldsUploaded !== null) {
            return $this->memoDocumentFieldsUploaded;
        }

        if (! filled($this->account_type)) {
            return $this->memoDocumentFieldsUploaded = 0;
        }

        $fields = \App\Support\BankOpening\DocumentRequirementCatalog::allKeysFor(
            $this->account_type,
            $this->meta
        );

        if ($fields === []) {
            return $this->memoDocumentFieldsUploaded = 0;
        }

        return $this->memoDocumentFieldsUploaded = $this->countPresentDocumentKeys($fields);
    }

    public function requiredDocumentsTotal(): int
    {
        if ($this->memoRequiredDocumentsTotal !== null) {
            return $this->memoRequiredDocumentsTotal;
        }

        if (! filled($this->account_type)) {
            return $this->memoRequiredDocumentsTotal = 0;
        }

        $keys = \App\Support\BankOpening\DocumentRequirementCatalog::requiredKeysFor(
            $this->account_type,
            $this->meta
        );

        return $this->memoRequiredDocumentsTotal = max(count($keys), 0);
    }

    public function requiredDocumentsUploadedCount(): int
    {
        if ($this->memoRequiredDocumentsUploaded !== null) {
            return $this->memoRequiredDocumentsUploaded;
        }

        if (! filled($this->account_type)) {
            return $this->memoRequiredDocumentsUploaded = 0;
        }

        $required = \App\Support\BankOpening\DocumentRequirementCatalog::requiredKeysFor(
            $this->account_type,
            $this->meta
        );

        if ($required === []) {
            return $this->memoRequiredDocumentsUploaded = 0;
        }

        return $this->memoRequiredDocumentsUploaded = $this->countPresentDocumentKeys($required);
    }

    /**
     * @param  list<string>  $keys
     */
    private function countPresentDocumentKeys(array $keys): int
    {
        $types = $this->relationLoaded('documents')
            ? $this->documents->whereNull('removed_at')->pluck('document_type')
            : ($this->relationLoaded('activeDocuments')
                ? $this->activeDocuments->pluck('document_type')
                : $this->documents()->active()->pluck('document_type'));

        $present = [];
        foreach ($types as $type) {
            foreach (\App\Support\BankOpening\DocumentRequirementCatalog::normalizeStoredType((string) $type) as $alias) {
                $present[$alias] = true;
            }
            $present[(string) $type] = true;
        }

        $count = 0;
        foreach ($keys as $key) {
            if (isset($present[$key])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return list<string> */
    public function missingRequiredDocumentKeys(): array
    {
        if (! filled($this->account_type)) {
            return [];
        }

        $required = \App\Support\BankOpening\DocumentRequirementCatalog::requiredKeysFor(
            $this->account_type,
            $this->meta
        );
        $uploaded = $this->requiredDocumentsUploadedCount();

        if ($uploaded >= count($required)) {
            return [];
        }

        $types = $this->documents()->active()->pluck('document_type');
        $present = [];
        foreach ($types as $type) {
            foreach (\App\Support\BankOpening\DocumentRequirementCatalog::normalizeStoredType((string) $type) as $alias) {
                $present[$alias] = true;
            }
            $present[(string) $type] = true;
        }

        return array_values(array_filter(
            $required,
            static fn (string $key) => ! isset($present[$key])
        ));
    }

    public const PUBLIC_LINK_HOURS_BEFORE_INTERVIEW = 48;

    public const PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION = 24;

    public function canEditDocuments(): bool
    {
        if ($this->stage === BankOpeningStage::ResubmissionRequired) {
            return true;
        }

        if ($this->isApplicationSubmitted()) {
            return false;
        }

        return $this->isInterviewFinished();
    }

    /**
     * Invite / pre-interview links last 48h; docs & resubmission links last 24h.
     */
    public function publicLinkValidityHours(): int
    {
        if ($this->stage === BankOpeningStage::ResubmissionRequired || $this->isInterviewFinished()) {
            return self::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION;
        }

        return self::PUBLIC_LINK_HOURS_BEFORE_INTERVIEW;
    }

    /**
     * Effective end of the document upload / resubmission window shown to applicants.
     * Uses the earlier of: public token expiry, or interview_completed_at + 24h (docs phase).
     */
    public function documentsWindowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $tokenExpiry = $this->public_token_expiry;

        if ($this->stage === BankOpeningStage::ResubmissionRequired) {
            return $tokenExpiry;
        }

        if ($this->interview_completed_at) {
            $fromInterview = $this->interview_completed_at->copy()->addHours(
                self::PUBLIC_LINK_HOURS_DOCS_OR_RESUBMISSION
            );

            if ($tokenExpiry) {
                return $fromInterview->lt($tokenExpiry) ? $fromInterview : $tokenExpiry->copy();
            }

            return $fromInterview;
        }

        return $tokenExpiry?->copy();
    }

    public function hasActivePublicLink(): bool
    {
        return filled($this->public_token_hash)
            && $this->public_token_expiry
            && $this->public_token_expiry->isFuture();
    }

    public function issuePublicToken(?int $hours = null): string
    {
        $hours = max(1, $hours ?? $this->publicLinkValidityHours());
        $expiry = now()->addHours($hours);
        $token = hash_hmac('sha256', $this->id.$expiry->timestamp, config('app.key'));

        $this->forceFill([
            'public_token_hash' => hash('sha256', $token),
            'public_token_expiry' => $expiry,
        ])->save();

        return $token;
    }

    public function reconstructPublicToken(): ?string
    {
        if (! $this->public_token_hash || ! $this->public_token_expiry) {
            return null;
        }

        $token = hash_hmac('sha256', $this->id.$this->public_token_expiry->timestamp, config('app.key'));

        if (hash('sha256', $token) !== $this->public_token_hash) {
            return null;
        }

        return $token;
    }

    public function publicUrl(): ?string
    {
        $token = $this->reconstructPublicToken();

        return $token ? url('/bank-opening/'.$token) : null;
    }

    public static function findByPublicToken(string $token): ?self
    {
        return static::query()
            ->where('public_token_hash', hash('sha256', $token))
            ->where('public_token_expiry', '>', now())
            ->first();
    }

    public function recordEvent(string $type, ?array $payload = null, ?int $createdBy = null): BankOpeningApplicationEvent
    {
        return $this->events()->create([
            'event_type' => $type,
            'payload' => $payload,
            'created_by' => $createdBy,
        ]);
    }
}
