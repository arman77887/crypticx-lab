<?php

namespace Tests\Unit;

use Tests\TestCase;

class PremiumQuotaContractTest extends TestCase
{
    public function test_quota_service_uses_user_owned_resources(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringContainsString(
            "->where('user_id', \$user->id)",
            $source,
        );

        $this->assertStringContainsString(
            "'targets_total'",
            $source,
        );

        $this->assertStringContainsString(
            "'reports_monthly'",
            $source,
        );

        $this->assertStringContainsString(
            "'monitoring_policies'",
            $source,
        );
    }

    public function test_report_quota_uses_generated_at_month_window(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringContainsString(
            "'generated_at'",
            $source,
        );

        $this->assertStringContainsString(
            'startOfMonth()',
            $source,
        );
    }

    public function test_monitoring_requires_plan_capability(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringContainsString(
            "'monitoring'",
            $source,
        );

        $this->assertStringContainsString(
            'PLAN_CAPABILITY_REQUIRED',
            $source,
        );
    }

    public function test_scanner_safety_config_is_not_modified_by_quota_service(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringNotContainsString(
            'scanner.max_concurrent_assessments',
            $source,
        );

        $this->assertStringNotContainsString(
            'ssrf',
            strtolower($source),
        );

        $this->assertStringNotContainsString(
            'private_network',
            strtolower($source),
        );
    }
}
