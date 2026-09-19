<?php

namespace Tests\Unit;

use Tests\TestCase;

class PlatformSecurityContractTest extends TestCase
{
    public function test_admin_device_gate_requires_bound_session(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Middleware/RequireTrustedAdminDevice.php'
            )
        );

        $this->assertStringContainsString(
            'currentAccessToken()',
            $source,
        );

        $this->assertStringContainsString(
            'trusted_device_id',
            $source,
        );

        $this->assertStringContainsString(
            'TRUSTED_DEVICE_SESSION_MISMATCH',
            $source,
        );
    }

    public function test_admin_finding_status_has_manage_permission_boundary(): void
    {
        $source = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "permission:findings.manage",
            $source,
        );

        $this->assertStringContainsString(
            "/admin/finding-lifecycles/{lifecycle}/status",
            $source,
        );
    }

    public function test_device_session_termination_contract_exists(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Services/DeviceTrustService.php'
            )
        );

        $this->assertStringContainsString(
            'terminateDeviceSessions',
            $source,
        );

        $this->assertStringContainsString(
            "'trusted_device_id'",
            $source,
        );
    }
}
