<?php

namespace App\Support\BankOpening;

/**
 * Account-type document groups for post-interview upload.
 * Keys are stored as bank_opening_documents.document_type.
 */
final class DocumentRequirementCatalog
{
    public const MAX_FILE_KB = 10240;

    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];

    /** Only these groups block Submit for now (main personal ID). */
    public const PRIMARY_REQUIRED_KEYS = [
        'applicant_photo_id',
        'applicant_nid_probashi',
        'birth_registration_student_id',
    ];

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   description: string|null,
     *   required: bool,
     *   max_files: int,
     *   conditional: bool
     * }>
     */
    public static function groupsForAccountType(?string $accountTypeSlug, ?array $meta = null): array
    {
        if (! filled($accountTypeSlug)) {
            return [];
        }

        $slug = $accountTypeSlug;
        $groups = match ($slug) {
            'youngsters_savings' => self::youngsters(),
            'shomota_savings' => self::shomota(),
            'pensioner_savings' => self::pensioner(),
            'ucb_nrb_savings' => self::nrb(),
            'ucb_probashi' => self::probashi(),
            'ucb_swadhin' => self::swadhin(),
            'rfcd' => self::rfcd(),
            'current_personal' => self::standardRetail(),
            'dynamic_benefits',
            'non_interest_savings',
            'sabuj_shanchay',
            'savings' => self::standardRetail(),
            default => self::standardRetail(),
        };

        // Shared "optional" packs hidden for now — still nullable if added later.
        if (self::hasOfficerAdditionalRequest($meta)) {
            $groups[] = [
                'key' => 'officer_additional',
                'label' => 'Additional document requested by officer',
                'description' => (string) data_get($meta, 'officer_document_request.reason', 'Please upload the document requested by the bank.'),
                'required' => true,
                'max_files' => 8,
                'conditional' => true,
            ];
        }

        // Resubmission: only reopen listed groups when present.
        $reopen = data_get($meta, 'resubmission.groups');
        if (is_array($reopen) && $reopen !== []) {
            $allowed = array_flip(array_map('strval', $reopen));
            $groups = array_values(array_filter(
                $groups,
                static fn (array $g) => isset($allowed[$g['key']])
            ));
        }

        return array_map(static function (array $group): array {
            $group['required'] = in_array($group['key'], self::PRIMARY_REQUIRED_KEYS, true)
                || ($group['key'] === 'officer_additional' && ($group['required'] ?? false));

            return $group;
        }, $groups);
    }

    /** @return list<string> */
    public static function requiredKeysFor(?string $accountTypeSlug, ?array $meta = null): array
    {
        return array_values(array_map(
            static fn (array $g) => $g['key'],
            array_filter(
                self::groupsForAccountType($accountTypeSlug, $meta),
                static fn (array $g) => $g['required'] === true
            )
        ));
    }

    /** All document fields for an account type (required + optional). */
    /** @return list<string> */
    public static function allKeysFor(?string $accountTypeSlug, ?array $meta = null): array
    {
        return array_values(array_map(
            static fn (array $g) => $g['key'],
            self::groupsForAccountType($accountTypeSlug, $meta)
        ));
    }

    /** @return list<string> */
    public static function allKnownKeys(): array
    {
        $keys = [];
        foreach (AccountProductCatalog::enabledProducts() as $product) {
            foreach (self::groupsForAccountType($product['slug']) as $group) {
                $keys[$group['key']] = true;
            }
        }
        $keys['officer_additional'] = true;
        // Legacy keys still accepted for older uploads.
        $keys['applicant_nid'] = true;
        $keys['nominee_nid'] = true;
        $keys['passport_photo'] = true;
        $keys['other'] = true;

        return array_keys($keys);
    }

    public static function labelFor(string $key): string
    {
        foreach (self::groupsForAccountType('savings') as $group) {
            if ($group['key'] === $key) {
                return $group['label'];
            }
        }

        foreach (AccountProductCatalog::enabledProducts() as $product) {
            foreach (self::groupsForAccountType($product['slug']) as $group) {
                if ($group['key'] === $key) {
                    return $group['label'];
                }
            }
        }

        return match ($key) {
            'applicant_nid' => 'Applicant photo ID',
            'nominee_nid' => 'Nominee photo ID',
            'passport_photo' => 'Applicant photograph',
            'officer_additional' => 'Additional document requested by officer',
            'other' => 'Other document',
            default => str_replace('_', ' ', ucwords($key, '_')),
        };
    }

    public static function maxFilesFor(string $key, ?string $accountTypeSlug = null, ?array $meta = null): int
    {
        foreach (self::groupsForAccountType($accountTypeSlug, $meta) as $group) {
            if ($group['key'] === $key) {
                return max(1, (int) $group['max_files']);
            }
        }

        return 5;
    }

    /**
     * Map legacy single-type uploads into current group keys for progress checks.
     *
     * @return list<string>
     */
    public static function normalizeStoredType(string $type): array
    {
        return match ($type) {
            'applicant_nid' => ['applicant_nid', 'applicant_photo_id', 'applicant_nid_probashi'],
            'nominee_nid' => ['nominee_nid', 'nominee_photo_id', 'nominee_id_and_photo'],
            'passport_photo' => ['passport_photo', 'applicant_photograph', 'student_photograph'],
            default => [$type],
        };
    }

    private static function hasOfficerAdditionalRequest(?array $meta): bool
    {
        return filled(data_get($meta, 'officer_document_request.reason'));
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function standardRetail(): array
    {
        return [
            self::group('applicant_photo_id', 'Applicant photo ID', 'NID, passport, or other government photo ID. Multiple pages allowed.', true, 5),
            self::group('applicant_photograph', 'Applicant passport-size photograph', null, true, 3),
            self::group('nominee_photo_id', 'Nominee photo ID', 'NID or other government photo ID. Multiple pages allowed.', true, 5),
            self::group('nominee_photograph', 'Nominee photograph', null, true, 3),
            self::group('proof_of_address', 'Proof of address / utility bill', 'Recent utility bill or other address proof.', true, 5),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function youngsters(): array
    {
        return [
            self::group('birth_registration_student_id', 'Birth registration & student identity', 'Birth registration and school/college identity evidence. Multiple files allowed.', true, 8),
            self::group('student_photograph', 'Student photograph', null, true, 3),
            self::group('guardian_id_and_photo', 'Guardian photo ID & photograph', 'Guardian ID and photo. Multiple files allowed.', true, 8),
            self::group('nominee_id_and_photo', 'Nominee ID & photograph', 'Nominee ID and photo. Multiple files allowed.', true, 8),
            self::group('guardian_address_income_proof', 'Guardian address & income proof', 'Address and income-proof documents. Multiple files allowed.', true, 8),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function shomota(): array
    {
        return [
            self::group('applicant_photo_id', 'Applicant photo ID', null, true, 5),
            self::group('eligibility_certificate', 'Eligibility ID / certificate', 'Upload only when applicable for your eligibility basis.', false, 5, true),
            self::group('applicant_photograph', 'Applicant photograph', null, true, 3),
            self::group('nominee_photo_id', 'Nominee photo ID', null, true, 5),
            self::group('nominee_photograph', 'Nominee photograph', null, true, 3),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function pensioner(): array
    {
        return [
            self::group('applicant_photo_id', 'Applicant photo ID', null, true, 5),
            self::group('applicant_photograph', 'Applicant photograph', null, true, 3),
            self::group('pension_retirement_proof', 'Pension / retirement proof', 'Pension book, retirement letter, or equivalent. Multiple pages allowed.', true, 8),
            self::group('nominee_id_and_photo', 'Nominee ID & photograph', 'Multiple files allowed.', true, 8),
            self::group('proof_of_address', 'Proof of address', null, true, 5),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function nrb(): array
    {
        return [
            self::group('applicant_photo_id', 'Applicant photo ID', null, true, 5),
            self::group('applicant_photograph', 'Applicant photograph', null, true, 3),
            self::group('nominee_id_and_photo', 'Nominee ID & photograph', 'Multiple files allowed.', true, 8),
            self::group('visa_work_permit_citizenship', 'Visa / work permit / citizenship evidence', 'Multiple pages allowed.', true, 8),
            self::group('proof_of_address', 'Proof of address', null, true, 5),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function probashi(): array
    {
        return [
            self::group('applicant_nid_probashi', 'Valid NID', 'National ID pages. Multiple pages allowed.', true, 5),
            self::group('applicant_photograph', 'Applicant face photograph', null, true, 3),
            self::group('nominee_photograph', 'Nominee photograph', null, true, 3),
            self::group('passport_info_page', 'Passport information page', 'Multiple pages allowed.', true, 5),
            self::group('visa_work_permit_citizenship', 'Visa / work permit / citizenship evidence', 'Multiple pages allowed.', true, 8),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function swadhin(): array
    {
        return [
            self::group('applicant_photo_id', 'Applicant photo ID', null, true, 5),
            self::group('freelancer_source_evidence', 'Freelancer / source-of-funds evidence', 'Freelancer ID, marketplace profile, work order, contract, or work communications. Multiple files allowed.', true, 10),
            self::group('applicant_photograph', 'Applicant photograph', null, true, 3),
            self::group('nominee_photo_id', 'Nominee photo ID', null, true, 5),
            self::group('nominee_photograph', 'Nominee photograph', null, true, 3),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function rfcd(): array
    {
        return [
            self::group('passport_visa_travel_pages', 'Passport with visa & arrival/departure pages', 'Multiple pages allowed.', true, 10),
            self::group('applicant_photograph', 'Applicant photograph', null, true, 3),
            self::group('foreign_currency_declaration', 'Foreign-currency declaration documents', 'Multiple files allowed.', true, 8),
            self::group('nominee_id_and_photo', 'Nominee ID & photograph', 'Multiple files allowed.', true, 8),
            self::group('proof_of_address', 'Proof of address', null, true, 5),
            self::group('fmj_customs_declaration', 'FMJ / customs declaration', 'Upload only when applicable.', false, 5, true),
        ];
    }

    /** @return list<array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}> */
    private static function sharedOptional(): array
    {
        return [
            self::group('tax_return_etin', 'Tax-return receipt or e-TIN', 'Optional — upload only if applicable.', false, 5, true),
            self::group('minor_nominee_guardian', 'Minor nominee guardian information & photograph', 'Optional — only when the nominee is a minor.', false, 8, true),
        ];
    }

    /**
     * @return array{key: string, label: string, description: string|null, required: bool, max_files: int, conditional: bool}
     */
    private static function group(
        string $key,
        string $label,
        ?string $description,
        bool $required,
        int $maxFiles,
        bool $conditional = false
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'required' => $required,
            'max_files' => $maxFiles,
            'conditional' => $conditional,
        ];
    }
}
