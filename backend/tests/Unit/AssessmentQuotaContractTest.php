<?php

namespace Tests\Unit;

use Tests\TestCase;

class AssessmentQuotaContractTest extends TestCase
{
    public function test_monthly_assessment_quota_is_user_scoped(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringContainsString(
            'assertAssessmentCreationAllowed',
            $source,
        );

        $this->assertStringContainsString(
            "'assessments_monthly'",
            $source,
        );

        $this->assertStringContainsString(
            "->where('user_id', \$user->id)",
            $source,
        );

        $this->assertStringContainsString(
            "'created_at'",
            $source,
        );

        $this->assertStringContainsString(
            'ASSESSMENT_MONTHLY_LIMIT_REACHED',
            $source,
        );
    }

    public function test_plan_concurrency_counts_only_running_assessments(): void
    {
        $source = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $this->assertStringContainsString(
            'hasAssessmentConcurrencyCapacity',
            $source,
        );

        $this->assertStringContainsString(
            "'concurrent_assessments'",
            $source,
        );

        $this->assertStringContainsString(
            "->where('status', 'running')",
            $source,
        );
    }

    public function test_dispatch_service_enforces_quota_before_creation(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Services/AssessmentDispatchService.php'
            )
        );

        $quota = strpos(
            $source,
            'assertAssessmentCreationAllowed'
        );

        $create = strpos(
            $source,
            'Assessment::create'
        );

        $this->assertNotFalse($quota);
        $this->assertNotFalse($create);
        $this->assertLessThan($create, $quota);

        $this->assertStringContainsString(
            'lockForUpdate()',
            $source,
        );
    }

    public function test_scheduled_monitoring_is_entitlement_gated(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Services/AssessmentDispatchService.php'
            )
        );

        $this->assertStringContainsString(
            'assertMonitoringExecutionAllowed',
            $source,
        );

        $this->assertStringContainsString(
            "source: 'monitoring'",
            $source,
        );
    }

    public function test_worker_checks_global_capacity_before_plan_capacity(): void
    {
        $source = file_get_contents(
            base_path('app/Jobs/RunAssessment.php')
        );

        $global = strpos(
            $source,
            '$concurrency->hasCapacity()'
        );

        $plan = strpos(
            $source,
            'hasAssessmentConcurrencyCapacity'
        );

        $running = strpos(
            $source,
            "'status' => 'running'"
        );

        $this->assertNotFalse($global);
        $this->assertNotFalse($plan);
        $this->assertNotFalse($running);

        $this->assertLessThan($plan, $global);
        $this->assertLessThan($running, $plan);
    }

    public function test_plan_logic_does_not_modify_scanner_safety_limit(): void
    {
        $quota = file_get_contents(
            base_path('app/Services/QuotaService.php')
        );

        $dispatch = file_get_contents(
            base_path(
                'app/Services/AssessmentDispatchService.php'
            )
        );

        $this->assertStringNotContainsString(
            'max_concurrent_assessments',
            $quota,
        );

        $this->assertStringNotContainsString(
            'max_concurrent_assessments',
            $dispatch,
        );
    }
}
