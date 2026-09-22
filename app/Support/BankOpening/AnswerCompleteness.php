<?php

namespace App\Support\BankOpening;

/**
 * Detect incomplete / non-answer fragments for interview validation.
 */
final class AnswerCompleteness
{
    private const FRAGMENTS = [
        'hello', 'hi', 'hey', 'start', 'ok', 'okay', 'yes', 'no', 'হ্যালো', 'হ্যালো।',
        'ঠিক আছে', 'ঠিক আছে।', 'শুরু', 'শুরু কর', 'শুরু করি', 'হ্যাঁ', 'না', 'অকে',
        'hmm', 'uhm', 'um', 'ah',
    ];

    public static function isIncompleteFragment(string $answer): bool
    {
        $trimmed = trim(mb_strtolower($answer));
        $trimmed = preg_replace('/[^\p{L}\p{N}\s]/u', '', $trimmed) ?? $trimmed;
        $trimmed = trim(preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed);

        if ($trimmed === '' || mb_strlen($trimmed) < 2) {
            return true;
        }

        if (in_array($trimmed, self::FRAGMENTS, true)) {
            return true;
        }

        // Bare tiny numbers without unit/context are usually STT fragments.
        if (preg_match('/^\d{1,2}$/', $trimmed) === 1) {
            return true;
        }

        return false;
    }

    public const AMOUNT_KEYS = [
        'opening_deposit',
        'expected_monthly_deposit',
        'expected_monthly_turnover',
        'expected_monthly_remittance',
        'expected_monthly_foreign_earnings',
        'currency_and_amount',
        'monthly_deposit',
        'monthly_txn_range',
        'salary_range',
        'monthly_receive',
        'funder_amount',
        'monthly_joint',
        'remittance_amount',
        'amount_frequency',
        'use_monthly',
        'average_balance',
        'initial_average_balance',
        'monthly_amount',
    ];

    public static function isAmountQuestion(string $questionKey): bool
    {
        return in_array($questionKey, self::AMOUNT_KEYS, true);
    }
}
