<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankOpeningInterviewTurn extends Model
{
    use HasUuids;

    protected $fillable = [
        'bank_opening_application_id',
        'speaker',
        'question_key',
        'question_text',
        'answer_text',
        'answer_raw',
        'answer_normalized',
        'answer_status',
        'client_turn_id',
        'sequence',
        'spoken_at',
    ];

    protected function casts(): array
    {
        return [
            'spoken_at' => 'datetime',
            'sequence' => 'integer',
            'answer_normalized' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(BankOpeningApplication::class, 'bank_opening_application_id');
    }
}
