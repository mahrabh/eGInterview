<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplicationEvent extends Model
{
    protected $fillable = [
        'loan_application_id',
        'event_type',
        'description',
        'payload',
        'created_by'
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
