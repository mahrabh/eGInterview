<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProductRule extends Model
{
    protected $fillable = [
        'loan_type',
        'interest_rate',
        'interest_method',
        'max_dbr_percentage',
        'min_income',
        'min_loan_amount',
        'max_loan_amount',
        'min_tenure_months',
        'max_tenure_months',
        'max_ltv_percentage',
        'version',
        'effective_date',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'max_dbr_percentage' => 'decimal:2',
        'min_income' => 'decimal:2',
        'min_loan_amount' => 'decimal:2',
        'max_loan_amount' => 'decimal:2',
        'max_ltv_percentage' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
