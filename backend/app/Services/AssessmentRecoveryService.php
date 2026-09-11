<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\DB;

class AssessmentRecoveryService
{
    /*
     * RunAssessment has a 120-second timeout and the database queue has
     * retry_after=180 seconds.
     *
     * Recovery deliberately waits beyond both boundaries. This avoids
     * declaring an assessment abandoned while its original queue
     * execution may still legitimately be terminating.
     */
    private const ABANDONED_AFTER_SECONDS = 240;

    public function reconcileAbandonedRunning(): array
    {
        $threshold = now()->subSeconds(
            self::ABANDONED_AFTER_SECONDS
        );

        $candidates = Assessment::query()
            ->where('status', 'running')
            ->whereNotNull('started_at')
            ->where(
                'started_at',
                '<=',
                $threshold
            )
            ->orderBy('started_at')
            ->limit(100)
            ->pluck('id');

        $recovered = 0;
        $skipped = 0;

        foreach ($candidates as $assessmentId) {
            $changed = DB::transaction(
                function () use (
                    $assessmentId,
                    $threshold
                ): bool {
                    $assessment = Assessment::query()
                        ->whereKey($assessmentId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $assessment ||
                        $assessment->status !== 'running' ||
                        ! $assessment->started_at ||
                        $assessment->started_at->gt(
                            $threshold
                        )
                    ) {
                        return false;
                    }

                    $metadata = is_array(
                        $assessment->execution_metadata
                    )
                        ? $assessment->execution_metadata
                        : [];

                    $assessment->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' =>
                            'Assessment execution was abandoned before completion.',
                        'execution_metadata' =>
                            array_merge(
                                $metadata,
                                [
                                    'recovery' => [
                                        'reason' =>
                                            'abandoned_running_execution',
                                        'recovered_at' =>
                                            now()->toIso8601String(),
                                        'automatic_retry' =>
                                            false,
                                    ],
                                ],
                            ),
                    ]);

                    return true;
                }
            );

            if ($changed) {
                $recovered++;
            } else {
                $skipped++;
            }
        }

        return [
            'evaluated' => $candidates->count(),
            'recovered' => $recovered,
            'skipped' => $skipped,
            'abandoned_after_seconds' =>
                self::ABANDONED_AFTER_SECONDS,
        ];
    }

    public function reconcileTerminalQueueFailure(
        string $assessmentId,
        ?\Throwable $exception = null
    ): bool {
        return DB::transaction(
            function () use (
                $assessmentId,
                $exception
            ): bool {
                $assessment = Assessment::query()
                    ->whereKey($assessmentId)
                    ->lockForUpdate()
                    ->first();

                /*
                 * Never rewrite completed, blocked or already-failed
                 * assessments. A running assessment may already have
                 * performed network activity, so it is handled only by
                 * the conservative abandoned-running reconciler.
                 */
                if (
                    ! $assessment ||
                    $assessment->status !== 'queued'
                ) {
                    return false;
                }

                $metadata = is_array(
                    $assessment->execution_metadata
                )
                    ? $assessment->execution_metadata
                    : [];

                $assessment->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'worker_id' => null,
                    'error_message' =>
                        'Assessment queue execution exhausted its retry attempts before scanner execution.',
                    'execution_metadata' =>
                        array_merge(
                            $metadata,
                            [
                                'recovery' => [
                                    'reason' =>
                                        'queue_attempts_exhausted',
                                    'recovered_at' =>
                                        now()->toIso8601String(),
                                    'automatic_retry' =>
                                        false,
                                    'exception' =>
                                        $exception
                                            ? get_class(
                                                $exception
                                            )
                                            : null,
                                ],
                            ],
                        ),
                ]);

                return true;
            }
        );
    }
}
