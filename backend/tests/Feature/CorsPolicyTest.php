<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsPolicyTest extends TestCase
{
    public function test_trusted_production_origin_is_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://crypticxlab.crxhub.org',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/auth/me');

        $response->assertNoContent();

        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'https://crypticxlab.crxhub.org'
        );
    }

    public function test_retired_duckdns_origin_is_not_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://crypticxlab.duckdns.org',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/auth/me');

        $response->assertNoContent();

        $this->assertFalse(
            $response->headers->has('Access-Control-Allow-Origin')
        );
    }

    public function test_arbitrary_foreign_origin_is_not_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/auth/me');

        $response->assertNoContent();

        $this->assertFalse(
            $response->headers->has('Access-Control-Allow-Origin')
        );
    }
}
