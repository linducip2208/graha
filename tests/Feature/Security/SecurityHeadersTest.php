<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_responses_include_security_headers(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_sensitive_routes_have_explicit_rate_limits(): void
    {
        $expected = [
            'piles.public' => 'throttle:60,1',
            'signatures.verify' => 'throttle:60,1',
            'global-search.query' => 'throttle:60,1',
            'storage.request-upload' => 'throttle:30,1',
            'storage.finalize-upload' => 'throttle:30,1',
            'files.download' => 'throttle:120,1',
        ];

        foreach ($expected as $name => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }
}
