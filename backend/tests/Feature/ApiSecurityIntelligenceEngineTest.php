<?php

namespace Tests\Feature;

use App\Services\Scanner\ApiSecurityIntelligenceEngine;
use Tests\TestCase;

class ApiSecurityIntelligenceEngineTest extends TestCase
{
    private function engine(): ApiSecurityIntelligenceEngine
    {
        return new ApiSecurityIntelligenceEngine();
    }

    public function test_json_content_type_is_classified(): void
    {
        $result = $this->engine()->analyze([
            'status' => 200,
            'content_type' =>
                'application/json; charset=utf-8',
        ]);

        $this->assertTrue(
            $result['classification']['json_response']
        );

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_problem_json_is_classified_as_json(): void
    {
        $result = $this->engine()->analyze([
            'content_type' =>
                'application/problem+json',
        ]);

        $this->assertTrue(
            $result['classification']['json_response']
        );
    }

    public function test_missing_auth_challenge_does_not_create_finding(): void
    {
        $result = $this->engine()->analyze([
            'status' => 200,
            'content_type' => 'application/json',
        ]);

        $this->assertNull(
            $result['authentication']['www_authenticate']
        );

        $this->assertSame(
            [],
            $result['findings']
        );
    }

    public function test_auth_challenge_is_informational_only(): void
    {
        $result = $this->engine()->analyze([
            'www_authenticate' =>
                'Bearer realm="api"',
        ]);

        $this->assertSame(
            'Bearer realm="api"',
            $result['authentication']['www_authenticate']
        );

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_trace_and_connect_advertisement_creates_one_finding(): void
    {
        $result = $this->engine()->analyze([
            'allow_header' =>
                'GET, POST, TRACE, CONNECT',
        ]);

        $this->assertSame(
            ['GET', 'POST', 'TRACE', 'CONNECT'],
            $result['methods']['advertised']
        );

        $this->assertSame(
            ['TRACE', 'CONNECT'],
            $result['methods']['risky']
        );

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            'Potentially risky HTTP methods advertised',
            $result['findings'][0]['title']
        );

        $this->assertSame(
            'medium',
            $result['findings'][0]['severity']
        );

        $this->assertSame(
            'high',
            $result['findings'][0]['confidence']
        );
    }

    public function test_normal_advertised_methods_do_not_create_finding(): void
    {
        $result = $this->engine()->analyze([
            'allow_header' =>
                'GET, POST, PUT, PATCH, DELETE',
        ]);

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_wildcard_cors_creates_low_finding(): void
    {
        $result = $this->engine()->analyze([
            'allow_origin' => '*',
        ]);

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            'Wildcard CORS policy advertised',
            $result['findings'][0]['title']
        );

        $this->assertSame(
            'low',
            $result['findings'][0]['severity']
        );
    }

    public function test_credentialed_wildcard_cors_creates_medium_finding(): void
    {
        $result = $this->engine()->analyze([
            'allow_origin' => '*',
            'allow_credentials' => 'true',
        ]);

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            'Credentialed wildcard CORS configuration',
            $result['findings'][0]['title']
        );

        $this->assertSame(
            'medium',
            $result['findings'][0]['severity']
        );

        $this->assertSame(
            'high',
            $result['findings'][0]['confidence']
        );
    }

    public function test_specific_cors_origin_does_not_create_finding(): void
    {
        $result = $this->engine()->analyze([
            'allow_origin' =>
                'https://app.example.test',
            'allow_credentials' => 'true',
        ]);

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_generic_server_name_does_not_create_version_finding(): void
    {
        $result = $this->engine()->analyze([
            'server' => 'nginx',
        ]);

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_explicit_server_version_creates_finding(): void
    {
        $result = $this->engine()->analyze([
            'server' => 'nginx/1.26.2',
        ]);

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            'Detailed server version disclosure',
            $result['findings'][0]['title']
        );

        $this->assertSame(
            'low',
            $result['findings'][0]['severity']
        );
    }

    public function test_powered_by_creates_framework_disclosure_finding(): void
    {
        $result = $this->engine()->analyze([
            'powered_by' => 'Express',
        ]);

        $this->assertSame(
            1,
            $result['finding_count']
        );

        $this->assertSame(
            'Application framework disclosure',
            $result['findings'][0]['title']
        );

        $this->assertSame(
            'Express',
            $result['findings'][0]['evidence']['x_powered_by']
        );
    }

    public function test_api_version_is_informational_only(): void
    {
        $result = $this->engine()->analyze([
            'api_version' => '2026-09',
        ]);

        $this->assertSame(
            '2026-09',
            $result['disclosure']['api_version']
        );

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }

    public function test_cache_control_is_informational_only(): void
    {
        $result = $this->engine()->analyze([
            'cache_control' => 'public, max-age=300',
        ]);

        $this->assertSame(
            'public, max-age=300',
            $result['cache']['cache_control']
        );

        $this->assertSame(
            0,
            $result['finding_count']
        );
    }
}
