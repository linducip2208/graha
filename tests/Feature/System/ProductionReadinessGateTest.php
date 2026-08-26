<?php

namespace Tests\Feature\System;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_verifier_is_read_only_and_passes_an_empty_dataset(): void
    {
        $this->artisan('inventory:verify')
            ->expectsOutputToContain('PASS - tidak ada anomali inventory.')
            ->assertExitCode(0);
    }

    public function test_foundation_verifier_is_read_only_and_passes_an_empty_dataset(): void
    {
        $this->artisan('foundation:verify')
            ->expectsOutputToContain('PASS - tidak ada anomali foundation.')
            ->assertExitCode(0);
    }

    public function test_production_check_fails_closed_in_test_configuration_without_leaking_secrets(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        config()->set('app.seed_demo_data', true);

        $this->artisan('production:check')
            ->expectsOutputToContain('NOT READY')
            ->doesntExpectOutputToContain((string) config('app.key'))
            ->assertExitCode(1);
    }
}
