<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LoanApplication extends Model
{
    use HasUuids;

    protected $fillable = [
        'loan_applicant_id',
        'loan_type',
        'requested_amount',
        'purpose',
        'employment_status',
        'income_source',
        'employer_name',
        'monthly_income',
        'other_monthly_income',
        'existing_emi',
        'tenure_months',
        'asset_value',
        'down_payment',
        'status',
        'submitted_at',
        'public_token_hash',
        'public_token_expiry',
        'transcript',
        'transcript_text',
        'extracted_data',
        'calculation_data',
        'outcome',
        'reason_codes',
        'rule_version_id',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'monthly_income' => 'decimal:2',
        'other_monthly_income' => 'decimal:2',
        'existing_emi' => 'decimal:2',
        'asset_value' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'submitted_at' => 'datetime',
        'public_token_expiry' => 'datetime',
        'extracted_data' => 'array',
        'calculation_data' => 'array',
        'reason_codes' => 'array',
    ];

    public function applicant()
    {
        return $this->belongsTo(LoanApplicant::class, 'loan_applicant_id');
    }

    public function rule()
    {
        return $this->belongsTo(LoanProductRule::class, 'rule_version_id');
    }

    public function events()
    {
        return $this->hasMany(LoanApplicationEvent::class);
    }
}
