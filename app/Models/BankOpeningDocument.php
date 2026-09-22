<?php

namespace App\Models;

use App\Support\BankOpening\DocumentRequirementCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BankOpeningDocument extends Model
{
    use HasUuids;

    /** @deprecated Use DocumentRequirementCatalog::requiredKeysFor() */
    public const REQUIRED_TYPES = [
        'applicant_photo_id',
        'applicant_photograph',
        'nominee_photo_id',
        'nominee_photograph',
        'proof_of_address',
    ];

    protected $fillable = [
        'bank_opening_application_id',
        'document_type',
        'original_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'removed_at',
        'replaced_by_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(BankOpeningApplication::class, 'bank_opening_application_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }

    public function isActive(): bool
    {
        return $this->removed_at === null;
    }

    public function label(): string
    {
        return DocumentRequirementCatalog::labelFor((string) $this->document_type);
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/');
    }

    /**
     * Officer-only authorized download route — never a public storage URL.
     */
    public function downloadUrl(): string
    {
        return route('bank-openings.documents.download', [
            'application' => $this->bank_opening_application_id,
            'document' => $this->id,
        ]);
    }

    public function temporaryUrl(): ?string
    {
        return $this->downloadUrl();
    }

    public function markRemoved(?string $replacedById = null): void
    {
        $this->forceFill([
            'removed_at' => now(),
            'replaced_by_id' => $replacedById,
        ])->save();
    }

    public function deleteStoredFile(): void
    {
        if ($this->disk_path && Storage::disk('local')->exists($this->disk_path)) {
            Storage::disk('local')->delete($this->disk_path);
        }
    }
}
