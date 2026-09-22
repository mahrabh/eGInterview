<?php

namespace App\Support\BankOpening;

/**
 * Narrow amount normalization for active interview answers (BN/EN).
 */
final class AmountNormalizer
{
    /**
     * @return array{amount: int, currency: string, display: string}|null
     */
    public static function parse(?string $text): ?array
    {
        $raw = trim((string) $text);
        if ($raw === '') {
            return null;
        }

        $normalized = self::normalizeDigits(mb_strtolower($raw));
        $normalized = str_replace([',', ' '], ['', ' '], $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        $amount = null;

        if (preg_match('/(?:দেড়|ded|der)\s*(?:লক্ষ|লাখ|লাক|lakh|lac)/u', $normalized)) {
            $amount = 150000;
        } elseif (preg_match('/(?:আড়াই|arai)\s*(?:লক্ষ|লাখ|লাক|lakh|lac)/u', $normalized)) {
            $amount = 250000;
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:কোটি|crore|koti)/u', $normalized, $m)) {
            $amount = (int) round(((float) $m[1]) * 10000000);
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:লক্ষ|লাখ|লাক|lakh|lac)/u', $normalized, $m)) {
            $amount = (int) round(((float) $m[1]) * 100000);
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:হাজার|hajar|hazar|thousand|\bk\b)/u', $normalized, $m)) {
            $amount = (int) round(((float) $m[1]) * 1000);
        } else {
            $words = self::bengaliWordAmount($normalized);
            if ($words !== null) {
                $amount = $words;
            } elseif (preg_match('/(\d{3,})/', preg_replace('/[^\d]/', '', $normalized) ?? '', $m)) {
                $amount = (int) $m[1];
            }
        }

        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => 'BDT',
            'display' => 'BDT '.number_format($amount),
        ];
    }

    private static function normalizeDigits(string $text): string
    {
        $bn = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($bn, $en, $text);
    }

    private static function bengaliWordAmount(string $text): ?int
    {
        $map = [
            'পাঁচ হাজার' => 5000,
            'পঞ্চ হাজার' => 5000,
            'দশ হাজার' => 10000,
            'বিশ হাজার' => 20000,
            'পঞ্চাশ হাজার' => 50000,
            'এক লক্ষ' => 100000,
            'এক লাখ' => 100000,
            'দুই লক্ষ' => 200000,
            'দুই লাখ' => 200000,
            'পাঁচ লক্ষ' => 500000,
            'পাঁচ লাখ' => 500000,
            'দশ লক্ষ' => 1000000,
            'দশ লাখ' => 1000000,
            'five thousand' => 5000,
            'ten thousand' => 10000,
            'fifty thousand' => 50000,
            'one lakh' => 100000,
            'two lakh' => 200000,
            'five lakh' => 500000,
            'ten lakh' => 1000000,
        ];

        foreach ($map as $phrase => $value) {
            if (str_contains($text, $phrase)) {
                return $value;
            }
        }

        return null;
    }
}
