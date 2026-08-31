<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanCalculationService
{
    private const POLICY_VERSION = 'STATIC_LOAN_POLICY_V1';

    private const OUTCOME_ELIGIBLE = 'Indicatively Eligible';
    private const OUTCOME_NOT_ELIGIBLE = 'Not Eligible Under Current Rules';
    private const OUTCOME_NEEDS_REVIEW = 'Needs Review';

    /**
     * Static indicative policy used by the application.
     *
     * Bangladesh Bank sets regulatory ceilings for selected products, but it
     * does not prescribe the interest rates or all of the income/DBR values
     * below. Those values are conservative internal assumptions and must be
     * approved by the participating bank before production lending decisions.
     * Percentages are stored as percentage points (35 means 35%, not 0.35).
     */
    private const RULES = [
        'personal' => [
            'name' => 'Personal loan',
            'max_dbr_percentage' => 35.0,
            'interest_rate' => 12.0,
            'interest_rate_type' => 'fixed',
            'interest_rate_source' => 'internal_static_assumption',
            'interest_method' => 'reducing_balance',
            'min_tenure_months' => 12,
            'max_tenure_months' => 60,
            'min_income' => 30000.0,
            'min_loan_amount' => 50000.0,
            // Current unsecured personal-loan ceiling: Tk 10 lac.
            'max_loan_amount' => 1000000.0,
            'security' => 'unsecured',
            'max_ltv_percentage' => null,
            'ltv_basis' => null,
            'policy_basis' => 'Internal static policy within applicable regulatory ceilings',
            'regulatory_reference' => 'BRPD-1 Circular Letter No. 15 (05 May 2026); BRPD-1 Circular Letter No. 21 (25 June 2026)',
            'version' => self::POLICY_VERSION,
        ],
        'car' => [
            'name' => 'Car loan',
            'max_dbr_percentage' => 40.0,
            'interest_rate' => 11.0,
            'interest_rate_type' => 'fixed',
            'interest_rate_source' => 'internal_static_assumption',
            'interest_method' => 'reducing_balance',
            'min_tenure_months' => 12,
            'max_tenure_months' => 72,
            'min_income' => 40000.0,
            'min_loan_amount' => 300000.0,
            // Conservative internal ceiling below the current general regulatory ceiling.
            'max_loan_amount' => 4000000.0,
            'security' => 'hypothecation of vehicle',
            // Conservative internal LTV below the current general regulatory ceiling.
            'max_ltv_percentage' => 50.0,
            'ltv_basis' => 'lower of declared purchase price and accepted bank valuation',
            'policy_basis' => 'Internal static policy within applicable regulatory ceilings',
            'regulatory_reference' => 'BRPD-1 Circular Letter No. 21 (25 June 2026)',
            'version' => self::POLICY_VERSION,
        ],
        'home' => [
            'name' => 'Home loan',
            'max_dbr_percentage' => 50.0,
            'interest_rate' => 9.5,
            'interest_rate_type' => 'fixed',
            'interest_rate_source' => 'internal_static_assumption',
            'interest_method' => 'reducing_balance',
            'min_tenure_months' => 60,
            'max_tenure_months' => 240,
            'min_income' => 50000.0,
            'min_loan_amount' => 1000000.0,
            // Conservative ceiling: the current bank-specific ceiling is Tk 20/30/40 million.
            'max_loan_amount' => 20000000.0,
            'security' => 'registered mortgage',
            'max_ltv_percentage' => 70.0,
            'ltv_basis' => 'lower of purchase price and accepted bank valuation',
            'policy_basis' => 'Internal static policy within applicable regulatory ceilings',
            'regulatory_reference' => 'BRPD-1 Circular Letter No. 01 (06 January 2026)',
            'version' => self::POLICY_VERSION,
        ],
    ];

    public function calculate(LoanApplication $application): bool
    {
        try {
            if (!is_array($application->extracted_data)) {
                return $this->markNeedsReview($application, 'Extracted interview data is missing or invalid.');
            }

            [$data, $values] = $this->normaliseExtractedData($application->extracted_data);
            [$data, $values] = $this->backfillFromApplicationScalars($application, $data);

            // Save canonical fields first. A calculation failure must never erase
            // successfully extracted information used to populate the report form.
            $application->extracted_data = $data;
            $application->save();

            $loanType = $values['loan_type'];
            if ($loanType === null || !isset(self::RULES[$loanType])) {
                return $this->markNeedsReview(
                    $application,
                    $loanType === null
                        ? 'Loan type is missing or could not be confirmed.'
                        : "No static policy exists for loan type: {$loanType}."
                );
            }

            $rule = self::RULES[$loanType];
            $qualityReasons = $this->dataQualityReasons($data, $values, $loanType);

            if ($qualityReasons !== []) {
                return $this->markNeedsReview($application, $qualityReasons);
            }

            $monthlyIncome = $values['monthly_income'];
            $otherIncome = $values['other_income'] ?? 0.0;
            $totalIncome = $monthlyIncome + $otherIncome;
            $existingEmi = $values['existing_emi'] ?? 0.0;
            $requestedAmount = $values['requested_amount'];
            $requestedTenure = $values['requested_tenure_months'];
            $assetValue = $values['asset_value'];
            $downPayment = $values['down_payment'];

            $currentDbr = ($existingEmi / $totalIncome) * 100;
            $maxAllowedTotalEmi = $totalIncome * ($rule['max_dbr_percentage'] / 100);
            $availableNewEmi = max(0.0, $maxAllowedTotalEmi - $existingEmi);

            $proposedEmi = $this->calculateEmi(
                $requestedAmount,
                $rule['interest_rate'],
                $requestedTenure,
                $rule['interest_method']
            );

            $projectedDbr = (($existingEmi + $proposedEmi) / $totalIncome) * 100;
            $affordablePrincipal = $this->calculatePrincipal(
                $availableNewEmi,
                $rule['interest_rate'],
                $requestedTenure,
                $rule['interest_method']
            );

            $ltvLimit = null;
            $ltvPercentage = null;
            $requiredDownPayment = null;

            if (in_array($loanType, ['car', 'home'], true) && $assetValue !== null && $assetValue > 0) {
                $ltvLimit = $assetValue * ($rule['max_ltv_percentage'] / 100);
                $ltvPercentage = ($requestedAmount / $assetValue) * 100;
                $requiredDownPayment = $assetValue - $ltvLimit;
            }

            $constraints = [
                'affordability' => max(0.0, $affordablePrincipal),
                'policy_maximum' => $rule['max_loan_amount'],
            ];

            if ($ltvLimit !== null) {
                $constraints['ltv_limit'] = $ltvLimit;
            }

            $indicativeMax = max(0.0, min($constraints));
            $eligibleAmount = max(0.0, min($requestedAmount, $indicativeMax));
            $bindingConstraints = $this->bindingConstraints($constraints, $indicativeMax);

            $failedReasons = [];

            if ($totalIncome < $rule['min_income']) {
                $failedReasons[] = sprintf(
                    'Total monthly income (%.2f) is below the minimum required (%.2f).',
                    $totalIncome,
                    $rule['min_income']
                );
            }

            if ($requestedAmount < $rule['min_loan_amount']) {
                $failedReasons[] = sprintf(
                    'Requested amount is below the minimum allowed (%.2f).',
                    $rule['min_loan_amount']
                );
            }

            if ($requestedAmount > $rule['max_loan_amount']) {
                $failedReasons[] = sprintf(
                    'Requested amount exceeds the maximum allowed (%.2f).',
                    $rule['max_loan_amount']
                );
            }

            if ($requestedTenure < $rule['min_tenure_months']) {
                $failedReasons[] = sprintf(
                    'Requested tenure is below the minimum allowed (%d months).',
                    $rule['min_tenure_months']
                );
            }

            if ($requestedTenure > $rule['max_tenure_months']) {
                $failedReasons[] = sprintf(
                    'Requested tenure exceeds the maximum allowed (%d months).',
                    $rule['max_tenure_months']
                );
            }

            if ($projectedDbr > $rule['max_dbr_percentage'] + 0.0001) {
                $failedReasons[] = sprintf(
                    'Projected DBR (%.2f%%) exceeds the maximum allowed (%.2f%%).',
                    $projectedDbr,
                    $rule['max_dbr_percentage']
                );
            }

            if ($ltvPercentage !== null && $ltvPercentage > $rule['max_ltv_percentage'] + 0.0001) {
                $failedReasons[] = sprintf(
                    'LTV (%.2f%%) exceeds the maximum allowed (%.2f%%).',
                    $ltvPercentage,
                    $rule['max_ltv_percentage']
                );
            }

            if ($requiredDownPayment !== null && $downPayment !== null && $downPayment + 0.01 < $requiredDownPayment) {
                $failedReasons[] = sprintf(
                    'Declared down payment (%.2f) is below the minimum equity required (%.2f).',
                    $downPayment,
                    $requiredDownPayment
                );
            }

            if ($requestedAmount > $indicativeMax + 0.01) {
                $failedReasons[] = sprintf(
                    'Requested amount exceeds the calculated indicative limit (%.2f).',
                    $indicativeMax
                );
            }

            if ($indicativeMax < $rule['min_loan_amount']) {
                $failedReasons[] = 'Calculated affordability is below the minimum loan amount.';
            }

            $failedReasons = array_values(array_unique($failedReasons));
            $outcome = $failedReasons === []
                ? self::OUTCOME_ELIGIBLE
                : self::OUTCOME_NOT_ELIGIBLE;

            $totalRepayment = $proposedEmi * $requestedTenure;
            $totalInterest = max(0.0, $totalRepayment - $requestedAmount);

            $calculationData = [
                'policy_version' => self::POLICY_VERSION,
                'policy_classification' => 'static_internal_policy_with_regulatory_guardrails',
                'loan_type' => $loanType,
                'total_monthly_income' => $this->money($totalIncome),
                'existing_monthly_obligations' => $this->money($existingEmi),
                'requested_amount' => $this->money($requestedAmount),
                'requested_tenure_months' => $requestedTenure,
                'annual_interest_rate_percentage' => $rule['interest_rate'],
                'interest_method' => $rule['interest_method'],
                'current_dbr_percentage' => $this->percentage($currentDbr),
                'max_allowed_total_emi' => $this->money($maxAllowedTotalEmi),
                'available_new_emi' => $this->money($availableNewEmi),
                'proposed_emi' => $this->money($proposedEmi),
                'projected_dbr_percentage' => $this->percentage($projectedDbr),
                'affordable_principal' => $this->money($affordablePrincipal),
                'asset_value' => $assetValue !== null ? $this->money($assetValue) : null,
                'declared_down_payment' => $downPayment !== null ? $this->money($downPayment) : null,
                'minimum_required_down_payment' => $requiredDownPayment !== null
                    ? $this->money($requiredDownPayment)
                    : null,
                'ltv_limit' => $ltvLimit !== null ? $this->money($ltvLimit) : null,
                'ltv_percentage' => $ltvPercentage !== null ? $this->percentage($ltvPercentage) : null,
                'total_repayment' => $this->money($totalRepayment),
                'total_interest' => $this->money($totalInterest),
                'indicative_max_loan_amount' => $this->money($indicativeMax),
                'eligible_amount' => $this->money($eligibleAmount),
                'binding_constraints' => $bindingConstraints,
                'rule_snapshot' => $rule,
            ];

            DB::transaction(function () use ($application, $calculationData, $outcome, $failedReasons, $data, $values): void {
                $this->syncScalarColumns($application, $values, $data);
                $application->rule_version_id = null;
                $application->calculation_data = $calculationData;
                $application->outcome = $outcome;
                $application->reason_codes = $failedReasons;
                // This is an indicative assessment, not final approval/rejection.
                $application->status = 'assessed';
                $application->save();

                LoanApplicationEvent::create([
                    'loan_application_id' => $application->id,
                    'event_type' => 'calculation_completed',
                    'description' => "Calculated indicative outcome: {$outcome}",
                ]);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::error('Loan calculation failed.', [
                'loan_application_id' => $application->id,
                'exception' => $exception,
            ]);

            return $this->markNeedsReview($application, 'Calculation failed. Please retry or review the application.');
        }
    }

    /**
     * Convert extractor/manual-input shapes into one canonical shape so the
     * report form can be populated automatically.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function normaliseExtractedData(array $data): array
    {
        $values = [
            'loan_type' => $this->normaliseLoanType($this->firstField($data, ['loan_type', 'product_type'])),
            'purpose' => $this->normaliseText($this->firstField($data, ['purpose', 'loan_purpose'])),
            'income_source' => $this->normaliseText($this->firstField($data, ['income_source', 'employment_type', 'occupation'])),
            'employer_name' => $this->normaliseText($this->firstField($data, ['employer_name', 'employer', 'business_name'])),
            'monthly_income' => $this->normaliseAmount($this->firstField($data, ['exact_monthly_income', 'monthly_income', 'net_monthly_income'])),
            'other_income' => $this->normaliseAmount($this->firstField($data, ['other_regular_monthly_income', 'other_income'])),
            'existing_emi' => $this->normaliseAmount($this->firstField($data, ['existing_monthly_obligations', 'existing_emi', 'monthly_debt'])),
            'requested_amount' => $this->normaliseAmount($this->firstField($data, ['requested_amount', 'loan_amount'])),
            'requested_tenure_months' => $this->normaliseTenure($this->firstField($data, ['requested_tenure', 'requested_tenure_months', 'tenure_months'])),
            'asset_value' => $this->normaliseAmount($this->firstField($data, ['accepted_asset_value', 'asset_value', 'property_value', 'vehicle_value'])),
            'down_payment' => $this->normaliseAmount($this->firstField($data, ['down_payment', 'declared_down_payment'])),
        ];

        $canonicalKeys = [
            'loan_type' => 'loan_type',
            'purpose' => 'purpose',
            'income_source' => 'income_source',
            'employer_name' => 'employer_name',
            'exact_monthly_income' => 'monthly_income',
            'monthly_income' => 'monthly_income',
            'other_regular_monthly_income' => 'other_income',
            'other_income' => 'other_income',
            'existing_monthly_obligations' => 'existing_emi',
            'existing_emi' => 'existing_emi',
            'requested_amount' => 'requested_amount',
            'requested_tenure' => 'requested_tenure_months',
            'requested_tenure_months' => 'requested_tenure_months',
            'asset_value' => 'asset_value',
            'down_payment' => 'down_payment',
        ];

        foreach ($canonicalKeys as $canonicalKey => $valueKey) {
            $data[$canonicalKey] = $this->canonicalField($data[$canonicalKey] ?? null, $values[$valueKey]);
        }

        return [$data, $values];
    }

    /** @return list<string> */
    private function dataQualityReasons(array $data, array $values, string $loanType): array
    {
        $reasons = [];

        if ($values['monthly_income'] === null) {
            $reasons[] = 'Monthly income is missing or invalid.';
        }

        if ($values['requested_amount'] === null) {
            $reasons[] = 'Requested loan amount is missing or invalid.';
        }

        if ($values['requested_tenure_months'] === null) {
            $reasons[] = 'Requested tenure is missing or invalid.';
        }

        if ($values['monthly_income'] !== null && $values['monthly_income'] <= 0) {
            $reasons[] = 'Monthly income must be greater than zero.';
        }

        if ($values['other_income'] !== null && $values['other_income'] < 0) {
            $reasons[] = 'Other monthly income cannot be negative.';
        }

        if ($values['existing_emi'] !== null && $values['existing_emi'] < 0) {
            $reasons[] = 'Existing monthly obligations cannot be negative.';
        }

        if ($values['requested_amount'] !== null && $values['requested_amount'] <= 0) {
            $reasons[] = 'Requested amount must be greater than zero.';
        }

        if ($values['requested_tenure_months'] !== null && $values['requested_tenure_months'] <= 0) {
            $reasons[] = 'Requested tenure must be greater than zero.';
        }

        if ($values['asset_value'] !== null && $values['asset_value'] <= 0) {
            $reasons[] = 'Asset value must be greater than zero.';
        }

        if ($values['down_payment'] !== null && $values['down_payment'] < 0) {
            $reasons[] = 'Down payment cannot be negative.';
        }

        return array_values(array_unique($reasons));
    }

    private function calculateEmi(float $principal, float $annualRate, int $months, string $method): float
    {
        if ($principal <= 0 || $months <= 0 || $annualRate < 0) {
            return 0.0;
        }

        $monthlyRate = ($annualRate / 100) / 12;

        if ($method === 'flat') {
            $totalInterest = $principal * ($annualRate / 100) * ($months / 12);

            return ($principal + $totalInterest) / $months;
        }

        if ($monthlyRate == 0.0) {
            return $principal / $months;
        }

        $factor = pow(1 + $monthlyRate, $months);

        return $principal * $monthlyRate * $factor / ($factor - 1);
    }

    private function calculatePrincipal(float $emi, float $annualRate, int $months, string $method): float
    {
        if ($emi <= 0 || $months <= 0 || $annualRate < 0) {
            return 0.0;
        }

        $monthlyRate = ($annualRate / 100) / 12;

        if ($method === 'flat') {
            $years = $months / 12;

            return ($emi * $months) / (1 + (($annualRate / 100) * $years));
        }

        if ($monthlyRate == 0.0) {
            return $emi * $months;
        }

        $factor = pow(1 + $monthlyRate, $months);

        return $emi * ($factor - 1) / ($monthlyRate * $factor);
    }

    /** @param array<string, float> $constraints */
    private function bindingConstraints(array $constraints, float $minimum): array
    {
        return array_values(array_keys(array_filter(
            $constraints,
            static fn (float $value): bool => abs($value - $minimum) <= 0.01
        )));
    }

    private function markNeedsReview(LoanApplication $application, string|array $reason): bool
    {
        $newReasons = is_array($reason) ? $reason : [$reason];
        // Store only the current validation result. Historical reasons remain in
        // LoanApplicationEvent and must not pollute a corrected retry.
        $reasons = array_values(array_unique($newReasons));

        DB::transaction(function () use ($application, $reasons): void {
            $application->outcome = self::OUTCOME_NEEDS_REVIEW;
            $application->status = 'needs_review';
            $application->reason_codes = $reasons;
            $application->calculation_data = [
                'policy_version' => self::POLICY_VERSION,
                'policy_classification' => 'static_internal_policy_with_regulatory_guardrails',
                'assessment_status' => 'needs_review',
            ];
            $application->save();

            LoanApplicationEvent::create([
                'loan_application_id' => $application->id,
                'event_type' => 'calculation_needs_review',
                'description' => implode(' ', $reasons),
            ]);
        });

        return false;
    }

    private function firstField(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function unwrapField(mixed $field): mixed
    {
        if (is_array($field) && array_key_exists('value', $field)) {
            return $field['value'];
        }

        return $field;
    }

    private function canonicalField(mixed $existing, mixed $value): array
    {
        if (is_array($existing)) {
            $existing['value'] = $value;

            return $existing;
        }

        return ['value' => $value];
    }

    private function normaliseLoanType(mixed $field): ?string
    {
        $value = $this->unwrapField($field);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));

        if (str_contains($value, 'personal') || str_contains($value, 'ব্যক্তিগত')) {
            return 'personal';
        }

        if (
            str_contains($value, 'car') ||
            str_contains($value, 'auto') ||
            str_contains($value, 'vehicle') ||
            str_contains($value, 'গাড়ি') ||
            str_contains($value, 'গাড়ি')
        ) {
            return 'car';
        }

        if (
            str_contains($value, 'home') ||
            str_contains($value, 'house') ||
            str_contains($value, 'housing') ||
            str_contains($value, 'বাড়ি') ||
            str_contains($value, 'বাড়ি')
        ) {
            return 'home';
        }

        return null;
    }

    private function normaliseText(mixed $field): ?string
    {
        $value = $this->unwrapField($field);

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normaliseAmount(mixed $field): ?float
    {
        $value = $this->unwrapField($field);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalised = $this->normaliseDigits($value);
        $normalised = function_exists('mb_strtolower')
            ? mb_strtolower(trim($normalised), 'UTF-8')
            : strtolower(trim($normalised));

        if ($normalised === '') {
            return null;
        }

        if (preg_match('/^(no|none|nil|zero|না|নেই|নাই|नहीं|शून्य)$/u', $normalised) === 1) {
            return 0.0;
        }

        // Ranges are ambiguous and must be confirmed instead of guessed.
        if (preg_match('/\d+(?:\.\d+)?\s*(?:-|–|—|to|থেকে)\s*\d+(?:\.\d+)?/u', $normalised) === 1) {
            return null;
        }

        $compact = str_replace(',', '', $normalised);
        if (is_numeric($compact)) {
            return (float) $compact;
        }

        $multiplier = 1.0;
        if (preg_match('/(crore|koti|কোটি|करोड़)/u', $normalised) === 1) {
            $multiplier = 10000000.0;
        } elseif (preg_match('/(lakh|lac|লাখ|लाख)/u', $normalised) === 1) {
            $multiplier = 100000.0;
        } elseif (preg_match('/(thousand|\bk\b|hajar|hazar|হাজার|हजार)/u', $normalised) === 1) {
            $multiplier = 1000.0;
        }

        $numberText = str_replace(',', '', $normalised);
        if (preg_match('/-?\d+(?:\.\d+)?/', $numberText, $matches) !== 1) {
            return null;
        }

        return (float) $matches[0] * $multiplier;
    }

    private function normaliseTenure(mixed $field): ?int
    {
        $unit = null;
        if (is_array($field)) {
            $unit = $field['unit'] ?? null;
        }

        $value = $this->unwrapField($field);
        $text = is_string($value) ? $this->normaliseDigits($value) : null;
        $amount = $this->normaliseAmount($value);

        if ($amount === null || $amount <= 0) {
            return null;
        }

        $unitText = trim((string) ($unit ?? $text ?? ''));
        $unitText = function_exists('mb_strtolower')
            ? mb_strtolower($unitText, 'UTF-8')
            : strtolower($unitText);

        if (preg_match('/(year|yr|years|বছর|সাল|वर्ष|साल)/u', $unitText) === 1) {
            return (int) round($amount * 12);
        }

        return (int) round($amount);
    }

    private function normaliseDigits(string $value): string
    {
        return strtr($value, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
            '०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4',
            '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9',
        ]);
    }

    private function normaliseConfidence(mixed $value): float
    {
        $value = $this->unwrapField($value);
        $confidence = is_numeric($value) ? (float) $value : 100.0;

        if ($confidence >= 0 && $confidence <= 1) {
            $confidence *= 100;
        }

        return max(0.0, min(100.0, $confidence));
    }

    /** @return list<string> */
    private function listValue(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value
        )));
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    private function percentage(float $value): float
    {
        return round($value, 4);
    }

    /** @param array<string, mixed> $data @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function backfillFromApplicationScalars(LoanApplication $application, array $data): array
    {
        $map = [
            'loan_type' => $application->loan_type,
            'loan_purpose' => $application->purpose,
            'requested_amount' => $application->requested_amount,
            'requested_tenure' => $application->tenure_months,
            'income_source' => $application->income_source ?? $application->employment_status,
            'employer_name' => $application->employer_name,
            'exact_monthly_income' => $application->monthly_income,
            'other_regular_monthly_income' => $application->other_monthly_income,
            'existing_monthly_obligations' => $application->existing_emi,
            'asset_value' => $application->asset_value,
            'down_payment' => $application->down_payment,
        ];

        foreach ($map as $key => $scalar) {
            if ($scalar === null || $scalar === '') {
                continue;
            }

            $data[$key] = $this->canonicalField($data[$key] ?? null, match ($key) {
                'requested_tenure' => (int) $scalar,
                'loan_type', 'loan_purpose', 'income_source', 'employer_name' => (string) $scalar,
                default => round((float) $scalar, 2),
            });
        }

        return $this->normaliseExtractedData($data);
    }

    /** @param array<string, mixed> $values @param array<string, mixed> $data */
    private function syncScalarColumns(LoanApplication $application, array $values, array $data): void
    {
        $application->loan_type = $values['loan_type'];
        $application->purpose = $values['purpose'];
        $application->requested_amount = $values['requested_amount'];
        $application->tenure_months = $values['requested_tenure_months'];
        $application->income_source = $values['income_source'];
        $application->employment_status = $values['income_source'];
        $application->employer_name = $values['employer_name'];
        $application->monthly_income = $values['monthly_income'];
        $application->other_monthly_income = $values['other_income'] ?? 0.0;
        $application->existing_emi = $values['existing_emi'] ?? 0.0;
        $application->asset_value = $values['asset_value'];
        $application->down_payment = $values['down_payment'];
        $application->extracted_data = $data;
    }
}
