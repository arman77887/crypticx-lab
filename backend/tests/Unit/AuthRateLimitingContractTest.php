<?php

namespace Tests\Unit;

use Tests\TestCase;

class AuthRateLimitingContractTest extends TestCase
{
    public function test_named_auth_limiters_are_registered(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Providers/AppServiceProvider.php'
            )
        );

        $this->assertStringContainsString(
            "RateLimiter::for(\n            'auth-login'",
            $source,
        );

        $this->assertStringContainsString(
            "RateLimiter::for(\n            'auth-register'",
            $source,
        );
    }

    public function test_auth_limiter_keys_hash_normalized_identity(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Providers/AppServiceProvider.php'
            )
        );

        $this->assertStringContainsString(
            "hash(\n                    'sha256'",
            $source,
        );

        $this->assertStringContainsString(
            'strtolower(',
            $source,
        );

        $this->assertStringContainsString(
            'trim(',
            $source,
        );

        $this->assertStringContainsString(
            "'|ip:'",
            $source,
        );
    }

    public function test_auth_routes_use_named_limiters(): void
    {
        $source = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "throttle:auth-login",
            $source,
        );

        $this->assertStringContainsString(
            "throttle:auth-register",
            $source,
        );

        $this->assertStringNotContainsString(
            ")->middleware('throttle:5,1');",
            $source,
        );

        $this->assertStringNotContainsString(
            ")->middleware('throttle:3,1');",
            $source,
        );
    }
}
