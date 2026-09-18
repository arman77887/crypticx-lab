<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\PhishingLinkAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhishingLinkAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyzer_does_not_perform_network_request(): void
    {
        $result = app(PhishingLinkAnalyzer::class)
            ->analyze('https://example.com/login');

        $this->assertFalse(
            $result['network_request_performed']
        );

        $this->assertSame(
            'example.com',
            $result['hostname']
        );
    }

    public function test_suspicious_static_url_receives_risk_indicators(): void
    {
        $result = app(PhishingLinkAnalyzer::class)->analyze(
            'http://192.0.2.10/login/verify/account'
        );

        $this->assertGreaterThanOrEqual(
            20,
            $result['risk_score']
        );

        $this->assertNotEmpty(
            $result['indicators']
        );
    }

    public function test_clean_url_is_not_declared_safe(): void
    {
        $result = app(PhishingLinkAnalyzer::class)
            ->analyze('https://example.com');

        $this->assertStringContainsString(
            'does not prove',
            strtolower($result['assessment'])
        );
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->postJson(
            '/api/v1/tools/phishing-link-analyzer',
            ['url' => 'https://example.com']
        )->assertUnauthorized();
    }

    public function test_authenticated_user_can_analyze_url(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/v1/tools/phishing-link-analyzer',
                ['url' => 'https://example.com']
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.network_request_performed',
                false
            );
    }

    public function test_non_http_scheme_is_rejected(): void
    {
        $this->expectException(
            \InvalidArgumentException::class
        );

        app(PhishingLinkAnalyzer::class)
            ->analyze('javascript:alert(1)');
    }
}
