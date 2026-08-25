<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Interview extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'candidate_name',
        'candidate_email',
        'applied_role',
        'job_description',
        'status',
        'user_id',
        'approved_questions',
        'public_url',
        'transcript_text',
        'evaluation_json',
        'candidate_photo',
        'link_expires_at',
    ];

    protected $casts = [
        'approved_questions' => 'array',
        'evaluation_json' => 'array',
        'link_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}