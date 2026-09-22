<?php

namespace App\Support\BankOpening;

/**
 * Central UCB account-type catalog and interview question profiles.
 * Single source of truth — do not duplicate lists in React.
 */
final class AccountProductCatalog
{
    public const BANK_CODE = 'UCB';

    public const BANK_NAME = 'United Commercial Bank PLC';

    public const DISPLAY_NAME = 'UCB Bank';

    public const CATALOG_VERSION = 'ucb-retail-v1';

    public const MAX_PLANNED_QUESTIONS = 4;

    public const MAX_FOLLOW_UPS = 1;

    /**
     * @return list<array{
     *   slug: string,
     *   label: string,
     *   group: string,
     *   group_label: string,
     *   short_description: string,
     *   question_profile: string,
     *   eligibility_note: string|null,
     *   enabled: bool
     * }>
     */
    public static function products(): array
    {
        return [
            [
                'slug' => 'savings',
                'label' => 'Savings Account',
                'group' => 'everyday',
                'group_label' => 'Everyday accounts',
                'short_description' => 'Standard savings. Opening balance BDT 2,000 urban / BDT 1,000 rural.',
                'question_profile' => 'standard_savings',
                'eligibility_note' => null,
                'enabled' => true,
            ],
            [
                'slug' => 'current_personal',
                'label' => 'Current Account',
                'group' => 'everyday',
                'group_label' => 'Everyday accounts',
                'short_description' => 'Personal current account for everyday transactions. Same opening-balance levels as savings.',
                'question_profile' => 'current_personal',
                'eligibility_note' => 'Personal applicants only.',
                'enabled' => true,
            ],
            [
                'slug' => 'dynamic_benefits',
                'label' => 'Dynamic Benefits Savings Account',
                'group' => 'everyday',
                'group_label' => 'Everyday accounts',
                'short_description' => 'Benefits savings with opening balance BDT 50,000.',
                'question_profile' => 'standard_savings',
                'eligibility_note' => 'Bangladeshi nationals, age 18+.',
                'enabled' => true,
            ],
            [
                'slug' => 'non_interest_savings',
                'label' => 'Savings Deposit Non-Interest',
                'group' => 'everyday',
                'group_label' => 'Everyday accounts',
                'short_description' => 'Savings without interest for applicants who prefer a non-interest account.',
                'question_profile' => 'standard_savings',
                'eligibility_note' => 'Age 18+.',
                'enabled' => true,
            ],
            [
                'slug' => 'sabuj_shanchay',
                'label' => 'Sabuj Shanchay Account',
                'group' => 'inclusion',
                'group_label' => 'Inclusion accounts',
                'short_description' => 'Minimum balance BDT 500. Monthly transaction limit BDT 100,000. No cheque book.',
                'question_profile' => 'standard_savings',
                'eligibility_note' => 'Bangladeshi nationals, age 18+.',
                'enabled' => true,
            ],
            [
                'slug' => 'youngsters_savings',
                'label' => 'UCB Youngsters Savings',
                'group' => 'inclusion',
                'group_label' => 'Inclusion accounts',
                'short_description' => 'For students under 18, operated by a legal guardian. Opening balance BDT 100.',
                'question_profile' => 'youngsters',
                'eligibility_note' => 'Student below 18; guardian operates the account.',
                'enabled' => true,
            ],
            [
                'slug' => 'shomota_savings',
                'label' => 'Shomota Savings Account',
                'group' => 'inclusion',
                'group_label' => 'Inclusion accounts',
                'short_description' => 'For eligible financial-inclusion, safety-net or lower-income applicants.',
                'question_profile' => 'shomota',
                'eligibility_note' => 'Eligibility is confirmed during interview and manual review.',
                'enabled' => true,
            ],
            [
                'slug' => 'pensioner_savings',
                'label' => 'Pensioner Savings Account',
                'group' => 'inclusion',
                'group_label' => 'Inclusion accounts',
                'short_description' => 'For retired or pension recipients. Opening balance BDT 5,000.',
                'question_profile' => 'pensioner',
                'eligibility_note' => 'Age 35+; retired or pension recipient.',
                'enabled' => true,
            ],
            [
                'slug' => 'ucb_nrb_savings',
                'label' => 'UCB NRB Savings',
                'group' => 'overseas',
                'group_label' => 'Overseas and foreign-currency accounts',
                'short_description' => 'Savings for Non-Resident Bangladeshis (NRB).',
                'question_profile' => 'nrb_probashi',
                'eligibility_note' => 'For Non-Resident Bangladeshis.',
                'enabled' => true,
            ],
            [
                'slug' => 'ucb_probashi',
                'label' => 'UCB Probashi Savings Account (UCB One)',
                'group' => 'overseas',
                'group_label' => 'Overseas and foreign-currency accounts',
                'short_description' => 'NRB account, age 18+, valid NID. Monthly limit BDT 100,000. No cheque book.',
                'question_profile' => 'nrb_probashi',
                'eligibility_note' => 'NRB, age 18+, valid NID.',
                'enabled' => true,
            ],
            [
                'slug' => 'ucb_swadhin',
                'label' => 'UCB Swadhin Account',
                'group' => 'overseas',
                'group_label' => 'Overseas and foreign-currency accounts',
                'short_description' => 'For Bangladeshi freelancers and service exporters. No opening deposit. Linked UCB Savings/Current required.',
                'question_profile' => 'swadhin',
                'eligibility_note' => 'Freelancer/service exporter with a linked UCB Savings or Current account.',
                'enabled' => true,
            ],
            [
                'slug' => 'rfcd',
                'label' => 'RFCD',
                'group' => 'overseas',
                'group_label' => 'Overseas and foreign-currency accounts',
                'short_description' => 'Resident foreign-currency deposit for residents returning with foreign currency.',
                'question_profile' => 'rfcd',
                'eligibility_note' => 'Resident Bangladeshi, age 18+, returning with foreign currency.',
                'enabled' => true,
            ],
            // Kept inactive until UCB confirms requirements.
            [
                'slug' => 'nfcd',
                'label' => 'NFCD',
                'group' => 'overseas',
                'group_label' => 'Overseas and foreign-currency accounts',
                'short_description' => 'Non-resident foreign-currency deposit (inactive pending confirmation).',
                'question_profile' => 'rfcd',
                'eligibility_note' => 'Not available in this version.',
                'enabled' => false,
            ],
        ];
    }

    /**
     * @return list<array{key: string, text: string}>
     */
    public static function questionsForProfile(string $profile): array
    {
        return match ($profile) {
            'standard_savings' => [
                ['key' => 'profession', 'text' => 'What is your profession or occupation?'],
                ['key' => 'source_of_funds', 'text' => 'What is your primary source of funds?'],
                ['key' => 'opening_deposit', 'text' => 'How much do you expect to deposit when opening the account?'],
                ['key' => 'expected_monthly_deposit', 'text' => 'Approximately how much do you expect to deposit each month?'],
            ],
            'current_personal' => [
                ['key' => 'profession', 'text' => 'What is your profession or occupation?'],
                ['key' => 'source_of_funds', 'text' => 'What is your primary source of funds?'],
                ['key' => 'opening_deposit', 'text' => 'How much do you expect to deposit when opening the account?'],
                ['key' => 'expected_monthly_turnover', 'text' => 'What approximate monthly transaction or turnover range do you expect?'],
            ],
            'youngsters' => [
                ['key' => 'student_date_of_birth', 'text' => 'What is the student’s date of birth?'],
                ['key' => 'educational_institution', 'text' => 'Which school, college or institution does the student attend?'],
                ['key' => 'guardian_name_and_relationship', 'text' => 'What is the guardian’s full name and relationship to the student?'],
                ['key' => 'guardian_source_of_funds', 'text' => 'What is the guardian’s primary source of funds?'],
            ],
            'shomota' => [
                ['key' => 'eligibility_basis', 'text' => 'Which eligibility basis applies to you for this account?'],
                ['key' => 'profession', 'text' => 'What is your profession or occupation?'],
                ['key' => 'source_of_funds', 'text' => 'What is your primary source of funds?'],
                ['key' => 'expected_monthly_deposit', 'text' => 'Approximately how much do you expect to deposit each month?'],
            ],
            'pensioner' => [
                ['key' => 'retirement_status', 'text' => 'Please confirm your retirement or pension status.'],
                ['key' => 'pension_source', 'text' => 'What is the source of your pension or retirement income?'],
                ['key' => 'opening_deposit', 'text' => 'How much do you expect to deposit when opening the account?'],
                ['key' => 'expected_monthly_deposit', 'text' => 'Approximately how much do you expect to deposit each month?'],
            ],
            'nrb_probashi' => [
                ['key' => 'country_of_residence', 'text' => 'In which country do you currently live?'],
                ['key' => 'overseas_occupation_and_source', 'text' => 'What is your overseas occupation and main source of funds?'],
                ['key' => 'opening_deposit', 'text' => 'How much do you expect to deposit when opening the account?'],
                ['key' => 'expected_monthly_remittance', 'text' => 'What approximate remittance amount do you expect each month?'],
            ],
            'swadhin' => [
                ['key' => 'freelance_or_service_type', 'text' => 'What type of freelance or service-export work do you do?'],
                ['key' => 'freelancer_evidence_type', 'text' => 'What evidence can you provide for your freelance or export income?'],
                ['key' => 'has_ucb_linked_account', 'text' => 'Do you already have a linked UCB Savings or Current account?'],
                ['key' => 'expected_monthly_foreign_earnings', 'text' => 'What approximate foreign earnings do you expect each month?'],
            ],
            'rfcd' => [
                ['key' => 'latest_return_date', 'text' => 'When did you last return to Bangladesh?'],
                ['key' => 'foreign_currency_source', 'text' => 'What is the source of the foreign currency?'],
                ['key' => 'currency_and_amount', 'text' => 'Which currency and what approximate amount will you deposit?'],
                ['key' => 'exceeds_usd_10000_equivalent', 'text' => 'Does the amount exceed the equivalent of USD 10,000?'],
            ],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    public static function enabledProducts(): array
    {
        return array_values(array_filter(self::products(), fn (array $p) => $p['enabled'] === true));
    }

    public static function find(string $slug): ?array
    {
        foreach (self::products() as $product) {
            if ($product['slug'] === $slug && $product['enabled']) {
                return $product;
            }
        }

        return null;
    }

    public static function isValidSlug(string $slug): bool
    {
        return self::find($slug) !== null;
    }

    public static function labelFor(?string $slug): ?string
    {
        if (! $slug) {
            return null;
        }

        foreach (self::products() as $product) {
            if ($product['slug'] === $slug) {
                return $product['label'];
            }
        }

        // Historical / legacy slugs from earlier catalogs.
        return str_replace('_', ' ', ucwords(str_replace('-', ' ', $slug)));
    }

    /**
     * @return list<array{group: string, group_label: string, products: list<array<string, mixed>>}>
     */
    public static function groupedForInterview(): array
    {
        $groups = [];

        foreach (self::enabledProducts() as $product) {
            $key = $product['group'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'group' => $product['group'],
                    'group_label' => $product['group_label'],
                    'products' => [],
                ];
            }
            $groups[$key]['products'][] = $product;
        }

        return array_values($groups);
    }

    /**
     * Soft voice/text filter — never auto-selects a single subtype from generic “savings”.
     *
     * @return list<string>
     */
    public static function matchHint(string $hint): array
    {
        $hay = mb_strtolower(trim($hint));
        if ($hay === '') {
            return [];
        }

        $matches = [];

        foreach (self::enabledProducts() as $product) {
            $slug = mb_strtolower($product['slug']);
            $label = mb_strtolower($product['label']);
            $needleParts = preg_split('/[\s\-_]+/u', $label) ?: [];

            if (str_contains($hay, $slug) || str_contains($hay, $label)) {
                $matches[] = $product['slug'];

                continue;
            }

            foreach ($needleParts as $part) {
                if (mb_strlen($part) >= 4 && str_contains($hay, $part)) {
                    $matches[] = $product['slug'];
                    break;
                }
            }
        }

        $matches = array_values(array_unique($matches));

        // Generic “savings” must not collapse to a single subtype (e.g. Dynamic Benefits).
        if ($matches === [] && preg_match('/\bsaving|savings\b/i', $hay)) {
            foreach (self::enabledProducts() as $product) {
                if (in_array($product['question_profile'], ['standard_savings', 'shomota', 'pensioner'], true)
                    || str_contains(mb_strtolower($product['label']), 'savings')) {
                    $matches[] = $product['slug'];
                }
            }
            $matches = array_values(array_unique($matches));
        }

        return $matches;
    }

    public static function liveInterviewGuide(): string
    {
        $lines = [
            'BANK: '.self::DISPLAY_NAME.' ('.self::BANK_CODE.') catalog '.self::CATALOG_VERSION,
            'ACCOUNT TYPES (applicant chooses ONE by speaking — never guess or recommend a “best” type):',
        ];

        foreach (self::enabledProducts() as $product) {
            $lines[] = "- {$product['slug']}: {$product['label']} — {$product['short_description']} [profile={$product['question_profile']}]";
        }

        $lines[] = '';
        $lines[] = 'QUESTION PROFILES (after the applicant clearly chooses and confirms ONE account type, ask ONLY that profile’s questions, IN ORDER, one at a time):';

        $profiles = [];
        foreach (self::enabledProducts() as $product) {
            $profiles[$product['question_profile']] = true;
        }

        foreach (array_keys($profiles) as $profile) {
            $lines[] = "Profile {$profile}:";
            foreach (self::questionsForProfile($profile) as $i => $q) {
                $lines[] = '  '.($i + 1).". ({$q['key']}) {$q['text']}";
            }
        }

        $lines[] = '';
        $lines[] = 'RULES: Never recommend an account type. Never say best/ideal/good fit. Say “account type,” not “product.”';
        $lines[] = 'If the applicant says a generic word like “savings,” list the matching savings options briefly and ask them to pick ONE — do not auto-pick.';
        $lines[] = 'Keep opening_deposit separate from monthly deposit, turnover or remittance keys.';

        return implode("\n", $lines);
    }

    /**
     * Deterministic slug inference from transcript (legacy / diagnostics only).
     * New interviews must not rely on this for selection.
     */
    public static function inferSlugFromTranscript(string $transcript): ?string
    {
        $hay = mb_strtolower($transcript);
        $best = null;
        $bestLen = 0;

        foreach (self::enabledProducts() as $product) {
            $label = mb_strtolower($product['label']);
            if ($label !== '' && str_contains($hay, $label) && mb_strlen($label) > $bestLen) {
                $best = $product['slug'];
                $bestLen = mb_strlen($label);
            }
        }

        if ($best) {
            return $best;
        }

        $matches = self::matchHint($hay);

        return count($matches) === 1 ? $matches[0] : null;
    }
}
