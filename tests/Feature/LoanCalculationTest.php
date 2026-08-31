<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\LoanApplicant;
use App\Models\LoanApplication;
use App\Services\LoanCalculationService;

class LoanCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->applicant = LoanApplicant::create([
            'name' => 'John Doe',
            'phone' => '01711111111',
            'application_reference' => 'LA-TEST',
            'created_by' => $this->user->id,
        ]);

        $this->application = LoanApplication::create([
            'loan_applicant_id' => $this->applicant->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function baseExtractedData(array $overrides = []): array
    {
        return array_merge([
            'loan_type' => ['value' => 'personal', 'evidence' => '', 'confidence' => 95],
            'loan_purpose' => ['value' => 'General', 'evidence' => '', 'confidence' => 95],
            'exact_monthly_income' => ['value' => 100000, 'evidence' => '', 'confidence' => 95],
            'income_source' => ['value' => 'Salaried', 'evidence' => '', 'confidence' => 95],
            'existing_monthly_obligations' => ['value' => 20000, 'evidence' => '', 'confidence' => 95],
            'requested_amount' => ['value' => 1000000, 'evidence' => '', 'confidence' => 95],
            'requested_tenure' => ['value' => 12, 'evidence' => '', 'confidence' => 95],
            'other_regular_monthly_income' => ['value' => 0, 'evidence' => '', 'confidence' => 95],
            'missing_fields' => [],
            'contradictions' => [],
            'needs_confirmation' => [],
            'confidence' => 95,
        ], $overrides);
    }

    public function test_missing_data_returns_needs_review()
    {
        $this->application->extracted_data = [
            'loan_type' => ['value' => 'personal', 'evidence' => '', 'confidence' => 90],
            'missing_fields' => ['exact_monthly_income'],
            'needs_confirmation' => ['exact_monthly_income'],
            'contradictions' => [],
        ];
        $this->application->save();

        $service = new LoanCalculationService();
        $service->calculate($this->application);

        $this->assertEquals('Needs Review', $this->application->fresh()->outcome);
    }

    public function test_dbr_boundaries()
    {
        $this->application->extracted_data = $this->baseExtractedData([
            'exact_monthly_income' => ['value' => 100000, 'evidence' => '', 'confidence' => 95],
            'existing_monthly_obligations' => ['value' => 20000, 'evidence' => '', 'confidence' => 95],
            'requested_amount' => ['value' => 1000000, 'evidence' => '', 'confidence' => 95],
            'requested_tenure' => ['value' => 12, 'evidence' => '', 'confidence' => 95],
        ]);
        $this->application->save();

        $service = new LoanCalculationService();
        $service->calculate($this->application);

        $fresh = $this->application->fresh();
        $this->assertEquals('Not Eligible Under Current Rules', $fresh->outcome);
        
        $calcData = $fresh->calculation_data;
        $this->assertLessThan(1000000, $calcData['indicative_max_loan_amount']);
    }

    public function test_ltv_cases()
    {
        $this->application->extracted_data = $this->baseExtractedData([
            'loan_type' => ['value' => 'car', 'evidence' => '', 'confidence' => 95],
            'exact_monthly_income' => ['value' => 200000, 'evidence' => '', 'confidence' => 95],
            'existing_monthly_obligations' => ['value' => 0, 'evidence' => '', 'confidence' => 95],
            'requested_amount' => ['value' => 1500000, 'evidence' => '', 'confidence' => 95],
            'requested_tenure' => ['value' => 60, 'evidence' => '', 'confidence' => 95],
            'asset_value' => ['value' => 2000000, 'evidence' => '', 'confidence' => 95],
            'down_payment' => ['value' => 500000, 'evidence' => '', 'confidence' => 95],
        ]);
        $this->application->save();

        $service = new LoanCalculationService();
        $service->calculate($this->application);

        $fresh = $this->application->fresh();
        $this->assertEquals('Not Eligible Under Current Rules', $fresh->outcome);
        
        $calcData = $fresh->calculation_data;
        $this->assertEquals(1000000, $calcData['ltv_limit']);
        $this->assertEquals(1000000, $calcData['eligible_amount']);
        $hasLtvReason = false;
        foreach ($fresh->reason_codes as $reason) {
            if (str_contains($reason, 'LTV')) {
                $hasLtvReason = true;
                break;
            }
        }
        $this->assertTrue($hasLtvReason, 'Missing LTV reason code. Actual: ' . implode(', ', $fresh->reason_codes));
    }

    public function test_perfect_application()
    {
        $this->application->extracted_data = $this->baseExtractedData([
            'loan_type' => ['value' => 'home', 'evidence' => '', 'confidence' => 95],
            'exact_monthly_income' => ['value' => 200000, 'evidence' => '', 'confidence' => 95],
            'existing_monthly_obligations' => ['value' => 0, 'evidence' => '', 'confidence' => 95],
            'requested_amount' => ['value' => 2000000, 'evidence' => '', 'confidence' => 95],
            'requested_tenure' => ['value' => 120, 'evidence' => '', 'confidence' => 95],
            'asset_value' => ['value' => 5000000, 'evidence' => '', 'confidence' => 95],
            'down_payment' => ['value' => 3000000, 'evidence' => '', 'confidence' => 95],
        ]);
        $this->application->save();

        $service = new LoanCalculationService();
        $service->calculate($this->application);

        $fresh = $this->application->fresh();
        $this->assertEquals('Indicatively Eligible', $fresh->outcome);
        $this->assertEquals(2000000, $fresh->calculation_data['eligible_amount']);
    }
}
