<?php

namespace App\Jobs;

use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentConcurrencyService;
use App\Services\ChangeDetectionService;
use App\Services\FindingLifecycleService;
use App\Services\HttpAssessmentService;
use App\Services\MonitoringNotificationPlannerService;
use App\Services\QuotaService;
use App\Services\AssessmentRecoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunAssessment implements ShouldQueue
{
    use Queueable;

    /*
     * Capacity-full releases consume queue attempts, so this job needs
     * enough attempts to wait for scanner capacity.
     *
     * Actual scanner failures are still re-thrown and are not converted
     * into successful or fake assessment results.
     */
    public int $tries = 25;

    public int $timeout = 120;

    public array $backoff = [
        15,
        15,
        30,
        30,
        60,
    ];

    public function __construct(
        public string $assessmentId,
    ) {
    }

    public function failed(?Throwable $exception): void
    {
        /*
         * This hook also runs when Laravel permanently fails the job
         * because its attempt budget is exhausted.
         *
         * Only a still-queued assessment is reconciled here. A running
         * assessment may have already performed network activity and is
         * therefore left to the conservative abandoned-execution
         * reconciler.
         */
        app(AssessmentRecoveryService::class)
            ->reconcileTerminalQueueFailure(
                $this->assessmentId,
                $exception
            );
    }


    public function handle(
        HttpAssessmentService $scanner,
        FindingLifecycleService $lifecycleService,
        ChangeDetectionService $changeDetection,
        MonitoringNotificationPlannerService $notificationPlanner,
        AssessmentConcurrencyService $concurrency,
        QuotaService $quotas
    ): void {
        /*
         * Atomically claim this assessment before any network activity.
         *
         * Queue systems may redeliver a job. The assessment row therefore
         * acts as the execution authority: only a queued assessment may
         * transition to running.
         *
         * Target ownership, authorization and active status are re-checked
         * while the assessment row is locked. This prevents execution when
         * security state changes after dispatch but before worker pickup.
         */
        $claim = DB::transaction(
            function () use ($concurrency, $quotas): array {
                $lockedAssessment = Assessment::query()
                    ->whereKey($this->assessmentId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Duplicate/stale queue delivery.
                 *
                 * Only queued assessments may compete for execution
                 * capacity.
                 */
                if ($lockedAssessment->status !== 'queued') {
                    return [
                        'state' => 'noop',
                        'assessment' => null,
                    ];
                }

                $target = $lockedAssessment->target()
                    ->lockForUpdate()
                    ->first();

                /*
                 * Security state is authoritative at worker pickup,
                 * not merely when the assessment was created.
                 */
                if (
                    ! $target ||
                    $target->user_id !== $lockedAssessment->user_id ||
                    ! $target->authorization_confirmed ||
                    $target->status !== 'active'
                ) {
                    $lockedAssessment->update([
                        'status' => 'blocked',
                        'completed_at' => now(),
                        'progress' => 0,
                        'worker_id' => null,
                        'error_message' =>
                            'Target is not authorized or active.',
                    ]);

                    return [
                        'state' => 'blocked',
                        'assessment' => null,
                    ];
                }

                /*
                 * Serialize concurrency admission across queue workers.
                 *
                 * If the platform is already at capacity, leave the
                 * assessment queued. No scanner/network activity occurs.
                 */
                if (! $concurrency->hasCapacity()) {
                    return [
                        'state' => 'capacity',
                        'assessment' => null,
                    ];
                }

                /*
                 * Global scanner capacity is the hard platform safety
                 * ceiling. Plan capacity is an additional per-user
                 * admission constraint and can never override it.
                 *
                 * AssessmentConcurrencyService obtains the PostgreSQL
                 * transaction-level advisory lock before this check.
                 * That lock remains held until this transaction ends,
                 * serializing worker admission while the per-user
                 * running count is evaluated and the row transitions
                 * to running.
                 */
                $assessmentUser = User::query()
                    ->whereKey($lockedAssessment->user_id)
                    ->first();

                if (
                    ! $assessmentUser ||
                    ! $quotas->hasAssessmentConcurrencyCapacity(
                        $assessmentUser,
                    )
                ) {
                    return [
                        'state' => 'capacity',
                        'assessment' => null,
                    ];
                }

                $lockedAssessment->update([
                    'status' => 'running',
                    'started_at' => now(),
                    'completed_at' => null,
                    'progress' => 5,
                    'worker_id' => 'http-worker-'.Str::uuid(),
                    'error_message' => null,
                ]);

                return [
                    'state' => 'claimed',
                    'assessment' =>
                        $lockedAssessment->fresh(['target']),
                ];
            }
        );

        /*
         * Capacity pressure is not a scan failure.
         *
         * Keep the assessment queued and make the database queue job
         * available again after a short delay.
         */
        if (($claim['state'] ?? null) === 'capacity') {
            $this->release(15);

            return;
        }

        $assessment = $claim['assessment'] ?? null;

        /*
         * null here means stale/duplicate delivery or a target that was
         * safely blocked before scanner execution.
         */
        if (! $assessment instanceof Assessment) {
            return;
        }

        try {
            $assessment->update([
                'progress' => 20,
            ]);

            $result = $scanner->run($assessment);

            /*
             * Lifecycle synchronization happens only after the scanner
             * itself completes successfully.
             *
             * This prevents a failed/partial assessment from resolving
             * findings that were simply not reached before failure.
             */
            $lifecycle = $lifecycleService->syncAssessment($assessment);

            $existingMetadata = is_array(
                $assessment->execution_metadata
            )
                ? $assessment->execution_metadata
                : [];

            $assessment->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now(),
                'execution_metadata' => array_merge(
                    $existingMetadata,
                    [
                        'engine' =>
                            $result['engine_version']
                            ?? 'http-assessment-v3',
                        'result' => $result,
                        'lifecycle' => $lifecycle,
                    ],
                ),
                'error_message' => null,
            ]);

            /*
             * Change detection is monitoring post-processing.
             *
             * The scanner and lifecycle synchronization have already
             * completed successfully at this point. A secondary
             * intelligence failure must therefore never rewrite a
             * successful assessment as failed.
             */
            $assessment->refresh();

            $metadata = is_array(
                $assessment->execution_metadata
            )
                ? $assessment->execution_metadata
                : [];

            if (
                ($metadata['dispatch_source'] ?? null)
                === 'monitoring'
            ) {
                try {
                    $changes = $changeDetection->detect(
                        $assessment
                    );

                    /*
                     * Plan notifications only from immutable,
                     * persisted change-event IDs returned by the
                     * detector. Notification planning is secondary
                     * post-processing and must never invalidate a
                     * successful assessment or change detection.
                     */
                    $notificationPlanning = [
                        'eligible_events' => 0,
                        'deliveries_planned' => 0,
                        'failed_events' => 0,
                    ];

                    foreach (
                        ($changes['event_ids'] ?? [])
                        as $changeEventId
                    ) {
                        try {
                            $changeEvent =
                                \App\Models\MonitoringChangeEvent::query()
                                    ->whereKey($changeEventId)
                                    ->where(
                                        'assessment_id',
                                        $assessment->id
                                    )
                                    ->first();

                            if (! $changeEvent) {
                                $notificationPlanning[
                                    'failed_events'
                                ]++;

                                continue;
                            }

                            $notificationPlanning[
                                'eligible_events'
                            ]++;

                            $delivery =
                                $notificationPlanner->plan(
                                    $changeEvent
                                );

                            if ($delivery !== null) {
                                $notificationPlanning[
                                    'deliveries_planned'
                                ]++;
                            }
                        } catch (Throwable $notificationError) {
                            report($notificationError);

                            $notificationPlanning[
                                'failed_events'
                            ]++;
                        }
                    }

                    $assessment->update([
                        'execution_metadata' => array_merge(
                            $metadata,
                            [
                                'change_detection' =>
                                    $changes,
                                'notification_planning' =>
                                    $notificationPlanning,
                            ],
                        ),
                    ]);
                } catch (Throwable $changeError) {
                    report($changeError);

                    /*
                     * Persist only bounded diagnostic metadata.
                     * Do not expose the raw exception message here.
                     */
                    $assessment->update([
                        'execution_metadata' => array_merge(
                            $metadata,
                            [
                                'change_detection' => [
                                    'available' => false,
                                    'failed' => true,
                                    'exception' =>
                                        get_class(
                                            $changeError
                                        ),
                                ],
                            ],
                        ),
                    ]);
                }
            }

            return;
        } catch (Throwable $e) {
            $existingMetadata = is_array(
                $assessment->execution_metadata
            )
                ? $assessment->execution_metadata
                : [];

            $assessment->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => Str::limit($e->getMessage(), 1000),
                'execution_metadata' => array_merge(
                    $existingMetadata,
                    [
                        'engine' => 'http-assessment-v3',
                        'exception' => get_class($e),
                    ],
                ),
            ]);

            throw $e;
        }
    }
}
