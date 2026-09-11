<?php

namespace Tests\Unit;

use App\Services\TelemetryService;
use PHPUnit\Framework\TestCase;

class TelemetrySanitizationTest extends TestCase
{
    public function test_sensitive_metadata_is_redacted_recursively(): void
    {
        $service = new TelemetryService();

        $clean = $service->sanitizeMetadata([
            'success' => true,
            'password' => 'super-secret',
            'nested' => [
                'device_token' => 'raw-device-token',
                'api_key' => 'raw-api-key',
                'safe' => 'visible',
            ],
        ]);

        $this->assertTrue($clean['success']);

        $this->assertSame(
            '[REDACTED]',
            $clean['password'],
        );

        $this->assertSame(
            '[REDACTED]',
            $clean['nested']['device_token'],
        );

        $this->assertSame(
            '[REDACTED]',
            $clean['nested']['api_key'],
        );

        $this->assertSame(
            'visible',
            $clean['nested']['safe'],
        );
    }

    public function test_bearer_credential_value_is_redacted(): void
    {
        $service = new TelemetryService();

        $clean = $service->sanitizeMetadata([
            'value' => 'Bearer abc123.secret',
        ]);

        $this->assertSame(
            '[REDACTED]',
            $clean['value'],
        );
    }

    public function test_safe_metadata_is_preserved(): void
    {
        $service = new TelemetryService();

        $clean = $service->sanitizeMetadata([
            'target_id' => 'abc',
            'status' => 'confirmed',
            'count' => 4,
        ]);

        $this->assertSame(
            [
                'target_id' => 'abc',
                'status' => 'confirmed',
                'count' => 4,
            ],
            $clean,
        );
    }
}
