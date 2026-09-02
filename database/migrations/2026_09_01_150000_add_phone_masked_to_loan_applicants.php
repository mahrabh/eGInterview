<?php

use App\Models\LoanApplicant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applicants', function (Blueprint $table) {
            $table->string('phone_masked', 20)->nullable()->after('phone_hash');
        });

        LoanApplicant::query()->each(function (LoanApplicant $applicant): void {
            if (!empty($applicant->getAttributes()['phone_masked'])) {
                return;
            }

            $rawPhone = $applicant->getAttributes()['phone'] ?? null;
            if (!is_string($rawPhone) || $rawPhone === '') {
                return;
            }

            $phone = null;

            try {
                $phone = Crypt::decryptString($rawPhone);
            } catch (\Throwable) {
                $digits = preg_replace('/\D/', '', $rawPhone);
                if (strlen($digits) >= 6) {
                    $phone = $digits;
                }
            }

            if (!is_string($phone) || $phone === '') {
                return;
            }

            LoanApplicant::query()
                ->whereKey($applicant->getKey())
                ->update(['phone_masked' => LoanApplicant::maskPhone($phone)]);
        });
    }

    public function down(): void
    {
        Schema::table('loan_applicants', function (Blueprint $table) {
            $table->dropColumn('phone_masked');
        });
    }
};
