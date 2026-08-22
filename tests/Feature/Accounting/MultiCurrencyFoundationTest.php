<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Services\FxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiCurrencyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_idr_rate_is_one_without_configuration(): void
    {
        $company = Company::create(['code' => 'FX', 'name' => 'FX']);
        $service = app(FxService::class);

        $this->assertSame('1', $service->rate($company->id, 'IDR', '2026-08-23'));
        $this->assertSame('125000.00', $service->toIdr('125000', 'IDR', $company->id, '2026-08-23'));
    }

    public function test_foreign_currency_requires_stored_rate(): void
    {
        $company = Company::create(['code' => 'FY', 'name' => 'FY']);
        $service = app(FxService::class);

        try {
            $service->rate($company->id, 'USD', '2026-08-23');
            $this->fail('Tanpa kurs tersimpan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('currency', $e->errors());
        }

        $service->putRate($company->id, 'USD', '2026-08-01', '16250.50', 'BI mid rate');
        $this->assertSame('16250.50', number_format((float) $service->rate($company->id, 'USD', '2026-08-15'), 2, '.', ''));

        $service->putRate($company->id, 'USD', '2026-08-20', '16300.00');
        $this->assertSame('16300.00', number_format((float) $service->rate($company->id, 'USD', '2026-08-21'), 2, '.', ''));
        $this->assertSame('16250.50', number_format((float) $service->rate($company->id, 'USD', '2026-08-19'), 2, '.', ''));
    }
}
