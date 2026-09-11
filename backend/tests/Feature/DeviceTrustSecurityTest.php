<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use Tests\TestCase;

class DeviceTrustSecurityTest extends TestCase
{
    public function test_correct_device_token_matches_hash(): void
    {
        $service = new DeviceTrustService();

        $plain = str_repeat('A', 64);

        $device = new TrustedDevice([
            'device_token_hash' => hash('sha256', $plain),
        ]);

        $this->assertTrue(
            $service->tokenMatches($device, $plain)
        );
    }

    public function test_wrong_device_token_does_not_match(): void
    {
        $service = new DeviceTrustService();

        $device = new TrustedDevice([
            'device_token_hash' => hash(
                'sha256',
                str_repeat('A', 64),
            ),
        ]);

        $this->assertFalse(
            $service->tokenMatches(
                $device,
                str_repeat('B', 64),
            )
        );
    }

    public function test_empty_and_oversized_tokens_fail_closed(): void
    {
        $service = new DeviceTrustService();

        $device = new TrustedDevice([
            'device_token_hash' => hash(
                'sha256',
                str_repeat('A', 64),
            ),
        ]);

        $this->assertFalse(
            $service->tokenMatches($device, '')
        );

        $this->assertFalse(
            $service->tokenMatches(
                $device,
                str_repeat(
                    'X',
                    DeviceTrustService::MAX_TOKEN_LENGTH + 1,
                ),
            )
        );
    }

    public function test_verified_unexpired_device_is_trusted(): void
    {
        $service = new DeviceTrustService();

        $device = new TrustedDevice([
            'is_trusted' => true,
            'verified_at' => now(),
            'expires_at' => now()->addHour(),
            'revoked_at' => null,
        ]);

        $this->assertTrue(
            $service->isTrusted($device)
        );
    }

    public function test_expired_device_is_not_trusted(): void
    {
        $service = new DeviceTrustService();

        $device = new TrustedDevice([
            'is_trusted' => true,
            'verified_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
            'revoked_at' => null,
        ]);

        $this->assertFalse(
            $service->isTrusted($device)
        );
    }

    public function test_revoked_device_is_not_trusted(): void
    {
        $service = new DeviceTrustService();

        $device = new TrustedDevice([
            'is_trusted' => true,
            'verified_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $service->isTrusted($device)
        );
    }

    public function test_admin_device_enforcement_defaults_off(): void
    {
        $this->assertArrayHasKey(
            'trusted_device_admin_enforcement',
            PlatformSettingsService::DEFAULTS,
        );

        $this->assertFalse(
            PlatformSettingsService::DEFAULTS[
                'trusted_device_admin_enforcement'
            ]
        );
    }
}
