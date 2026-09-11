<?php

namespace App\Services;

use App\Exceptions\PlanQuotaException;
use App\Models\Assessment;
use App\Models\MonitoringPolicy;
use App\Models\Report;
use App\Models\Target;
use App\Models\User;

class QuotaService
{
    public function __construct(
        private EntitlementService $entitlements,
    ) {
    }

    public function assertTargetCreationAllowed(
        User $user,
    ): void {
        $this->assertCapability(
            $user,
            'target_management',
        );

        $limit = $this->entitlements->limit(
            $user,
            'targets_total',
        );

        if ($limit === null) {
            return;
        }

        $used = Target::query()
            ->where('user_id', $user->id)
            ->count();

        if ($used >= $limit) {
            throw new PlanQuotaException(
                'Your plan target limit has been reached.',
                'TARGET_LIMIT_REACHED',
                422,
            );
        }
    }

    public function assertAssessmentCreationAllowed(
        User $user,
    ): void {
        $this->assertCapability(
            $user,
            'assessments',
        );

        $limit = $this->entitlements->limit(
            $user,
            'assessments_monthly',
        );

        if ($limit === null) {
            return;
        }

        $monthStart = now()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        $used = Assessment::query()
            ->where('user_id', $user->id)
            ->where(
                'created_at',
                '>=',
                $monthStart,
            )
            ->where(
                'created_at',
                '<',
                $nextMonth,
            )
            ->count();

        if ($used >= $limit) {
            throw new PlanQuotaException(
                'Your monthly assessment limit has been reached.',
                'ASSESSMENT_MONTHLY_LIMIT_REACHED',
                422,
            );
        }
    }

    public function hasAssessmentConcurrencyCapacity(
        User $user,
    ): bool {
        if (
            ! $this->entitlements->allows(
                $user,
                'assessments',
            )
        ) {
            return false;
        }

        $limit = $this->entitlements->limit(
            $user,
            'concurrent_assessments',
        );

        if ($limit === null) {
            return true;
        }

        if ($limit <= 0) {
            return false;
        }

        $running = Assessment::query()
            ->where('user_id', $user->id)
            ->where('status', 'running')
            ->count();

        return $running < $limit;
    }

    public function assertMonitoringExecutionAllowed(
        User $user,
    ): void {
        $this->assertCapability(
            $user,
            'monitoring',
        );
    }

    public function assertReportGenerationAllowed(
        User $user,
    ): void {
        $this->assertCapability(
            $user,
            'reports',
        );

        $limit = $this->entitlements->limit(
            $user,
            'reports_monthly',
        );

        if ($limit === null) {
            return;
        }

        $used = Report::query()
            ->where('user_id', $user->id)
            ->where(
                'generated_at',
                '>=',
                now()->startOfMonth(),
            )
            ->where(
                'generated_at',
                '<',
                now()->copy()->startOfMonth()->addMonth(),
            )
            ->count();

        if ($used >= $limit) {
            throw new PlanQuotaException(
                'Your monthly report limit has been reached.',
                'REPORT_MONTHLY_LIMIT_REACHED',
                422,
            );
        }
    }

    public function assertMonitoringAllowed(
        User $user,
        ?string $existingPolicyId = null,
    ): void {
        $this->assertCapability(
            $user,
            'monitoring',
        );

        $limit = $this->entitlements->limit(
            $user,
            'monitoring_policies',
        );

        if ($limit === null) {
            return;
        }

        /*
         * Updating an already-existing policy does not consume another
         * policy slot. New policies do.
         */
        if ($existingPolicyId !== null) {
            return;
        }

        $used = MonitoringPolicy::query()
            ->where('user_id', $user->id)
            ->count();

        if ($used >= $limit) {
            throw new PlanQuotaException(
                'Your monitoring policy limit has been reached.',
                'MONITORING_POLICY_LIMIT_REACHED',
                422,
            );
        }
    }

    public function usage(
        User $user,
    ): array {
        $monthStart = now()->startOfMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        return [
            'targets_total' => Target::query()
                ->where('user_id', $user->id)
                ->count(),

            'assessments_monthly' => Assessment::query()
                ->where('user_id', $user->id)
                ->where(
                    'created_at',
                    '>=',
                    $monthStart,
                )
                ->where(
                    'created_at',
                    '<',
                    $nextMonth,
                )
                ->count(),

            'concurrent_assessments' => Assessment::query()
                ->where('user_id', $user->id)
                ->where('status', 'running')
                ->count(),

            'reports_monthly' => Report::query()
                ->where('user_id', $user->id)
                ->where(
                    'generated_at',
                    '>=',
                    $monthStart,
                )
                ->where(
                    'generated_at',
                    '<',
                    $nextMonth,
                )
                ->count(),

            'monitoring_policies' =>
                MonitoringPolicy::query()
                    ->where(
                        'user_id',
                        $user->id,
                    )
                    ->count(),
        ];
    }

    private function assertCapability(
        User $user,
        string $capability,
    ): void {
        if (
            $this->entitlements->allows(
                $user,
                $capability,
            )
        ) {
            return;
        }

        throw new PlanQuotaException(
            'This capability is not available on your current plan.',
            'PLAN_CAPABILITY_REQUIRED',
            403,
        );
    }
}
