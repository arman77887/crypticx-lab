<?php

namespace App\Services;

use App\Exceptions\PlanQuotaException;

use App\Jobs\RunAssessment;
use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AssessmentDispatchService
{
    private const PROFILES = [
        'discovery',
        'standard',
        'deep',
    ];

    public function __construct(
        private PlatformSettingsService $settings,
        private QuotaService $quotas,
    ) {
    }

    /**
     * Queue a manually requested assessment.
     *
     * Manual assessments preserve the existing API behavior:
     * they are not rejected merely because another assessment
     * for the same target is already queued/running.
     */
    public function dispatchManual(
        User $user,
        Target $target,
        string $profile,
        array $configuration = [],
    ): Assessment {
        return $this->dispatch(
            user: $user,
            target: $target,
            profile: $profile,
            configuration: $configuration,
            preventOverlap: false,
            source: 'manual',
        );
    }

    /**
     * Queue an assessment created by monitoring.
     *
     * Scheduled assessments use overlap protection so a slow
     * worker or queue backlog cannot continuously enqueue scans
     * for the same target.
     */
    public function dispatchScheduled(
        User $user,
        Target $target,
        string $profile,
        array $configuration = [],
    ): ?Assessment {
        try {
            $this->quotas->assertMonitoringExecutionAllowed(
                $user,
            );

            return $this->dispatch(
                user: $user,
                target: $target,
                profile: $profile,
                configuration: $configuration,
                preventOverlap: true,
                source: 'monitoring',
            );
        } catch (PlanQuotaException) {
            /*
             * Scheduled monitoring is entitlement-aware.
             *
             * A disabled capability or exhausted monthly quota simply
             * prevents creation of another autonomous assessment.
             */
            return null;
        }
    }

    private function dispatch(
        User $user,
        Target $target,
        string $profile,
        array $configuration,
        bool $preventOverlap,
        string $source,
    ): ?Assessment {
        if (! $this->settings->boolean(
            'assessment_creation_enabled',
            true
        )) {
            throw new RuntimeException(
                'New assessments are currently disabled by platform policy.'
            );
        }

        if ($target->user_id !== $user->id) {
            throw new RuntimeException(
                'Target does not belong to this user.'
            );
        }

        if (
            ! $target->authorization_confirmed ||
            $target->status !== 'active'
        ) {
            throw new RuntimeException(
                'Target is not authorized or active.'
            );
        }

        if (! in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException(
                'Unsupported assessment profile.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $target,
            $profile,
            $configuration,
            $preventOverlap,
            $source,
        ) {
            /*
             * Lock the target row so concurrent scheduler executions
             * serialize their overlap check for this target.
             */
            $lockedTarget = Target::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Re-check security state inside the lock. Authorization
             * or target status may have changed since initial loading.
             */
            if (
                $lockedTarget->user_id !== $user->id ||
                ! $lockedTarget->authorization_confirmed ||
                $lockedTarget->status !== 'active'
            ) {
                throw new RuntimeException(
                    'Target is not authorized or active.'
                );
            }

            if ($preventOverlap) {
                $activeExists = Assessment::query()
                    ->where('target_id', $lockedTarget->id)
                    ->whereIn('status', [
                        'queued',
                        'running',
                    ])
                    ->exists();

                if ($activeExists) {
                    return null;
                }
            }

            /*
             * Serialize assessment quota admission for this user.
             *
             * The user-row lock prevents two concurrent requests for
             * different targets from both passing the same monthly
             * quota count before either assessment is persisted.
             */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->quotas->assertAssessmentCreationAllowed(
                $lockedUser,
            );

            $assessment = Assessment::create([
                'user_id' => $user->id,
                'target_id' => $lockedTarget->id,
                'profile' => $profile,
                'status' => 'queued',
                'queued_at' => now(),
                'progress' => 0,
                'configuration' => $configuration,
                'execution_metadata' => [
                    'dispatch_source' => $source,
                ],
            ]);

            /*
             * Dispatch only after the database transaction commits.
             * The worker must never receive an assessment ID whose
             * database row is still uncommitted.
             */
            DB::afterCommit(function () use ($assessment): void {
                RunAssessment::dispatch($assessment->id);
            });

            return $assessment;
        });
    }
}
