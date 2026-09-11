<?php

namespace Tests\Unit;

use Tests\TestCase;

class PremiumEntitlementContractTest extends TestCase
{
    public function test_entitlement_endpoint_is_authenticated(): void
    {
        $routes = file_get_contents(
            base_path('routes/api.php')
        );

        $this->assertStringContainsString(
            "/account/entitlements",
            $routes,
        );

        $this->assertStringContainsString(
            "'auth:sanctum'",
            $routes,
        );
    }

    public function test_security_boundaries_are_not_plan_capabilities(): void
    {
        $config = file_get_contents(
            base_path('config/premium.php')
        );

        $forbiddenEntitlements = [
            'ssrf_bypass',
            'private_network_access',
            'authorization_bypass',
            'runtime_isolation_bypass',
            'resource_safety_bypass',
        ];

        foreach ($forbiddenEntitlements as $name) {
            $this->assertStringNotContainsString(
                "'".$name."'",
                $config,
            );
        }
    }
}
