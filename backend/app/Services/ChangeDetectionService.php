<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\FindingLifecycle;
use App\Models\MonitoringChangeEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChangeDetectionService
{
    public function __construct(
        protected AssessmentIntelligenceService $intelligence,
    ) {
    }

    /**
     * Persist immutable monitoring changes for a completed assessment.
     *
     * This service consumes lifecycle/intelligence state. It does not
     * implement a second finding lifecycle.
     */
    public function detect(Assessment $assessment): array
    {
        if ($assessment->status !== 'completed') {
            throw new InvalidArgumentException(
                'Change detection requires a completed assessment.'
            );
        }

        $comparisonResult =
            $this->intelligence->compareWithPrevious($assessment);

        if (! ($comparisonResult['available'] ?? false)) {
            return [
                'available' => false,
                'reason' =>
                    $comparisonResult['reason']
                    ?? 'no_previous_completed_assessment',
                'assessment_id' => $assessment->id,
                'events_created' => 0,
                'events_existing' => 0,
                'event_ids' => [],
                'event_types' => [],
            ];
        }

        $comparison = $comparisonResult['comparison'];

        return DB::transaction(function () use (
            $assessment,
            $comparison
        ): array {
            $previousId =
                $comparison['previous_assessment']['id'];

            $created = 0;
            $existing = 0;
            $eventIds = [];
            $types = [];

            foreach ($comparison['new'] as $finding) {
                $lifecycle = FindingLifecycle::query()
                    ->where('target_id', $assessment->target_id)
                    ->where(
                        'fingerprint',
                        $finding['fingerprint']
                    )
                    ->first();

                if ($lifecycle === null) {
                    continue;
                }

                $type =
                    $lifecycle->first_assessment_id ===
                        $assessment->id
                        ? 'finding_new'
                        : 'finding_reappeared';

                [$wasCreated, $event] = $this->persist(
                    $assessment,
                    $previousId,
                    $type,
                    $finding['fingerprint'],
                    [
                        'finding' => $finding,
                        'lifecycle' => [
                            'status' => $lifecycle->status,
                            'occurrence_count' =>
                                $lifecycle->occurrence_count,
                            'first_assessment_id' =>
                                $lifecycle->first_assessment_id,
                            'last_assessment_id' =>
                                $lifecycle->last_assessment_id,
                        ],
                    ],
                );

                $eventIds[] = $event->id;
                $wasCreated ? $created++ : $existing++;
                $types[$type] = ($types[$type] ?? 0) + 1;
            }

            /*
             * Reopened is a lifecycle transition, not merely a pairwise
             * assessment difference.
             *
             * A finding may exist in both the previous and current
             * assessments while having been explicitly resolved between
             * them. In that case AssessmentIntelligence classifies it as
             * persistent, but lifecycle synchronization correctly marks
             * it reopened.
             */
            $reopenedLifecycles = FindingLifecycle::query()
                ->where('target_id', $assessment->target_id)
                ->where('status', 'reopened')
                ->where(
                    'last_assessment_id',
                    $assessment->id
                )
                ->get();

            foreach ($reopenedLifecycles as $lifecycle) {
                $finding = $assessment->findings()
                    ->where(
                        'fingerprint',
                        $lifecycle->fingerprint
                    )
                    ->first();

                if ($finding === null) {
                    continue;
                }

                [$wasCreated, $event] = $this->persist(
                    $assessment,
                    $previousId,
                    'finding_reopened',
                    $lifecycle->fingerprint,
                    [
                        'finding' => [
                            'id' => $finding->id,
                            'fingerprint' =>
                                $finding->fingerprint,
                            'type' => $finding->type,
                            'title' => $finding->title,
                            'severity' => $finding->severity,
                            'confidence' =>
                                $finding->confidence,
                            'status' => $finding->status,
                        ],
                        'lifecycle' => [
                            'status' => $lifecycle->status,
                            'occurrence_count' =>
                                $lifecycle->occurrence_count,
                            'first_assessment_id' =>
                                $lifecycle->first_assessment_id,
                            'last_assessment_id' =>
                                $lifecycle->last_assessment_id,
                            'reopened_at' =>
                                $lifecycle->reopened_at
                                    ?->toISOString(),
                        ],
                        'semantics' => [
                            'transition' =>
                                'resolved_to_reopened',
                            'source' =>
                                'finding_lifecycle',
                        ],
                    ],
                );

                $eventIds[] = $event->id;
                $wasCreated ? $created++ : $existing++;

                $types['finding_reopened'] =
                    ($types['finding_reopened'] ?? 0) + 1;
            }

            foreach (
                $comparison['no_longer_detected']
                as $finding
            ) {
                [$wasCreated, $event] = $this->persist(
                    $assessment,
                    $previousId,
                    'finding_no_longer_detected',
                    $finding['fingerprint'],
                    [
                        'finding' => $finding,
                        'semantics' => [
                            'absence_means' =>
                                'no_longer_detected',
                            'absence_does_not_prove' =>
                                'resolved',
                        ],
                    ],
                );

                $eventIds[] = $event->id;
                $wasCreated ? $created++ : $existing++;

                $types['finding_no_longer_detected'] =
                    ($types['finding_no_longer_detected'] ?? 0)
                    + 1;
            }

            /*
             * A persistent finding may also have changed severity,
             * confidence, title or type. Those details remain in the
             * immutable comparison payload but do not create another V1
             * event type yet.
             */

            $risk = $comparison['risk'];

            if (($risk['delta_points'] ?? 0) != 0) {
                /*
                 * Synthetic fingerprint makes the DB unique constraint
                 * effective for this non-finding event.
                 */
                $riskFingerprint = hash(
                    'sha256',
                    'risk_changed:'.$assessment->id
                );

                [$wasCreated, $event] = $this->persist(
                    $assessment,
                    $previousId,
                    'risk_changed',
                    $riskFingerprint,
                    [
                        'risk' => $risk,
                        'semantics' => [
                            'unit' => 'risk_points',
                            'probability' => false,
                            'historical_snapshot' => false,
                            'source' =>
                                'current_lifecycle_scoring',
                        ],
                    ],
                );

                $eventIds[] = $event->id;
                $wasCreated ? $created++ : $existing++;
                $types['risk_changed'] =
                    ($types['risk_changed'] ?? 0) + 1;
            }

            return [
                'available' => true,
                'assessment_id' => $assessment->id,
                'previous_assessment_id' => $previousId,
                'events_created' => $created,
                'events_existing' => $existing,
                'event_ids' => array_values(
                    array_unique($eventIds)
                ),
                'event_types' => $types,
                'risk' => [
                    'delta_points' =>
                        $risk['delta_points'] ?? 0,
                    'trend' =>
                        $risk['trend'] ?? 'unchanged',
                    'historical_snapshot' => false,
                ],
            ];
        });
    }

    /**
     * @return array{0: bool, 1: MonitoringChangeEvent}
     */
    private function persist(
        Assessment $assessment,
        string $previousAssessmentId,
        string $eventType,
        string $fingerprint,
        array $payload,
    ): array {
        $event = MonitoringChangeEvent::query()
            ->firstOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'event_type' => $eventType,
                    'fingerprint' => $fingerprint,
                ],
                [
                    'user_id' => $assessment->user_id,
                    'target_id' => $assessment->target_id,
                    'previous_assessment_id' =>
                        $previousAssessmentId,
                    'payload' => $payload,
                    'detected_at' =>
                        $assessment->completed_at ?? now(),
                ],
            );

        return [$event->wasRecentlyCreated, $event];
    }
}
