<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequence_is_unique_and_padded(): void
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        NumberSequence::create(['company_id' => $c->id, 'document_type' => 'po', 'prefix' => 'PO', 'padding' => 4, 'last_reset_year' => 2026]);
        $s = app(NumberSequenceService::class);
        $this->assertSame('PO/2026/0001', $s->next($c->id, 'po', 2026));
        $this->assertSame('PO/2026/0002', $s->next($c->id, 'po', 2026));
    }
}
