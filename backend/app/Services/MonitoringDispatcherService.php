<?php

namespace App\Services;

use App\Models\MonitoringPolicy;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringDispatcherService
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private AssessmentDispatchService $assessments,
    ) {
    }

    /**
     * Evaluate and dispatch currently-due monitoring policies.
     *
     * Returns operational counters only. Individual policy failures do
     * not abort processing of the remaining due policies.
     */
    public function dispatchDue(?array $onlyPolicyIds = null): array
    {
        $query = MonitoringPolicy::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());

        /*
         * Optional explicit scope for deterministic callers such as
         * regression tests. Production scheduler calls dispatchDue()
         * without this argument and therefore retains the canonical
         * platform-wide behavior.
         */
        if ($onlyPolicyIds !== null) {
            if ($onlyPolicyIds === []) {
                return [
                    'evaluated' => 0,
                    'dispatched' => 0,
                    'overlap_skipped' => 0,
                    'disabled_or_not_due' => 0,
                    'target_blocked' => 0,
                    'failed' => 0,
                ];
            }

            $query->whereIn('id', $onlyPolicyIds);
        }

        $policyIds = $query
            ->orderBy('next_run_at')
            ->limit(self::BATCH_SIZE)
            ->pluck('id');

        $result = [
            'evaluated' => 0,
            'dispatched' => 0,
            'overlap_skipped' => 0,
            'disabled_or_not_due' => 0,
            'target_blocked' => 0,
            'failed' => 0,
        ];

        foreach ($policyIds as $policyId) {
            $result['evaluated']++;

            try {
                $outcome = $this->dispatchPolicy(
                    (string) $policyId
                );

                if (array_key_exists($outcome, $result)) {
                    $result[$outcome]++;
                }
            } catch (Throwable $e) {
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function dispatchPolicy(string $policyId): string
    {
        return DB::transaction(function () use ($policyId) {
            $policy = MonitoringPolicy::query()
                ->with([
                    'user',
                    'target',
                ])
                ->whereKey($policyId)
                ->lockForUpdate()
                ->first();

            if (! $policy) {
                return 'disabled_or_not_due';
            }

            /*
             * Another scheduler process may have handled this policy
             * after the initial due-policy query.
             */
            if (
                ! $policy->enabled ||
                $policy->next_run_at === null ||
                $policy->next_run_at->isFuture()
            ) {
                return 'disabled_or_not_due';
            }

            if (
                ! $policy->user ||
                ! $policy->target ||
                $policy->target->user_id !== $policy->user_id ||
                ! $policy->target->authorization_confirmed ||
                $policy->target->status !== 'active'
            ) {
                /*
                 * Do not keep repeatedly evaluating a target that is
                 * no longer eligible for autonomous scanning.
                 *
                 * Re-enabling monitoring will require an explicit
                 * policy update after authorization/status is valid.
                 */
                $policy->update([
                    'enabled' => false,
                    'next_run_at' => null,
                ]);

                return 'target_blocked';
            }

            $assessment = $this->assessments->dispatchScheduled(
                $policy->user,
                $policy->target,
                $policy->profile,
                $policy->configuration ?? [],
            );

            /*
             * Advance the schedule even when overlap protection skips
             * this occurrence. This prevents a due policy from being
             * re-evaluated every minute while a previous scan runs.
             */
            $scheduledAt = now();

            $policy->last_scheduled_at = $scheduledAt;
            $policy->next_run_at = $scheduledAt->copy()->addMinutes(
                $policy->interval_minutes
            );

            if ($assessment) {
                $policy->last_assessment_id = $assessment->id;
            }

            $policy->save();

            return $assessment
                ? 'dispatched'
                : 'overlap_skipped';
        });
    }
}
