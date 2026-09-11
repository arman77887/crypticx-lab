<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use InvalidArgumentException;

class AssessmentIntelligenceService
{
    public function __construct(
        protected RiskScoringService $riskScoring,
        protected AggregateRiskService $aggregateRisk,
    ) {
    }
    /**
     * Compare two completed assessments belonging to the same target.
     *
     * Finding identity is the existing deterministic fingerprint.
     * Absence in the current assessment is deliberately described as
     * "no_longer_detected", not automatically "resolved".
     */
    public function compare(
        Assessment $previous,
        Assessment $current
    ): array {
        $this->assertComparable($previous, $current);

        $previousFindings = Finding::query()
            ->where('assessment_id', $previous->id)
            ->whereNotNull('fingerprint')
            ->get()
            ->keyBy('fingerprint');

        $currentFindings = Finding::query()
            ->where('assessment_id', $current->id)
            ->whereNotNull('fingerprint')
            ->get()
            ->keyBy('fingerprint');

        $previousFingerprints = $previousFindings->keys();
        $currentFingerprints = $currentFindings->keys();

        $newFingerprints = $currentFingerprints
            ->diff($previousFingerprints)
            ->values();

        $persistentFingerprints = $currentFingerprints
            ->intersect($previousFingerprints)
            ->values();

        $noLongerDetectedFingerprints = $previousFingerprints
            ->diff($currentFingerprints)
            ->values();

        $new = $newFingerprints
            ->map(fn (string $fingerprint) =>
                $this->serializeFinding(
                    $currentFindings->get($fingerprint)
                )
            )
            ->values();

        $persistent = $persistentFingerprints
            ->map(function (string $fingerprint) use (
                $previousFindings,
                $currentFindings
            ) {
                $before = $previousFindings->get($fingerprint);
                $after = $currentFindings->get($fingerprint);

                return [
                    'fingerprint' => $fingerprint,
                    'previous' => $this->serializeFinding($before),
                    'current' => $this->serializeFinding($after),
                    'changes' => $this->findingChanges(
                        $before,
                        $after
                    ),
                ];
            })
            ->values();

        $noLongerDetected = $noLongerDetectedFingerprints
            ->map(fn (string $fingerprint) =>
                $this->serializeFinding(
                    $previousFindings->get($fingerprint)
                )
            )
            ->values();

        $previousRisk = $this->assessmentRisk(
            $previous,
            $previousFindings->values()
        );

        $currentRisk = $this->assessmentRisk(
            $current,
            $currentFindings->values()
        );

        $riskDelta =
            $currentRisk['score'] - $previousRisk['score'];

        $riskTrend = match (true) {
            $riskDelta > 0 => 'increased',
            $riskDelta < 0 => 'decreased',
            default => 'unchanged',
        };

        return [
            'previous_assessment' =>
                $this->serializeAssessment($previous),

            'current_assessment' =>
                $this->serializeAssessment($current),

            'risk' => [
                'previous' => $previousRisk,
                'current' => $currentRisk,
                'delta_points' => $riskDelta,
                'trend' => $riskTrend,
                'semantics' => [
                    'unit' => 'risk_points',
                    'probability' => false,
                    'historical_snapshot' => false,
                    'source' => 'current_lifecycle_scoring',
                ],
            ],

            'summary' => [
                'previous_findings' => $previousFindings->count(),
                'current_findings' => $currentFindings->count(),
                'new' => $new->count(),
                'persistent' => $persistent->count(),
                'no_longer_detected' =>
                    $noLongerDetected->count(),
                'net_change' =>
                    $currentFindings->count()
                    - $previousFindings->count(),
            ],

            'new' => $new->all(),
            'persistent' => $persistent->all(),
            'no_longer_detected' =>
                $noLongerDetected->all(),

            'semantics' => [
                'identity' => 'finding_fingerprint',
                'absence_means' => 'no_longer_detected',
                'absence_does_not_prove' => 'resolved',
            ],
        ];
    }

    /**
     * Compare an assessment with the immediately preceding completed
     * assessment for the same target.
     */
    public function compareWithPrevious(
        Assessment $current
    ): array {
        $previous = Assessment::query()
            ->where('target_id', $current->target_id)
            ->where('id', '!=', $current->id)
            ->where('status', 'completed')
            ->where(function ($query) use ($current) {
                if ($current->completed_at !== null) {
                    $query->where(
                        'completed_at',
                        '<',
                        $current->completed_at
                    );
                } else {
                    $query->where(
                        'created_at',
                        '<',
                        $current->created_at
                    );
                }
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($previous === null) {
            return [
                'available' => false,
                'reason' => 'no_previous_completed_assessment',
                'current_assessment' =>
                    $this->serializeAssessment($current),
            ];
        }

        return [
            'available' => true,
            'comparison' => $this->compare(
                $previous,
                $current
            ),
        ];
    }

    private function assertComparable(
        Assessment $previous,
        Assessment $current
    ): void {
        if ($previous->id === $current->id) {
            throw new InvalidArgumentException(
                'An assessment cannot be compared with itself.'
            );
        }

        if ($previous->target_id !== $current->target_id) {
            throw new InvalidArgumentException(
                'Assessments must belong to the same target.'
            );
        }

        if (
            $previous->status !== 'completed' ||
            $current->status !== 'completed'
        ) {
            throw new InvalidArgumentException(
                'Only completed assessments can be compared.'
            );
        }
    }

    private function assessmentRisk(
        Assessment $assessment,
        $findings
    ): array {
        $fingerprints = collect($findings)
            ->pluck('fingerprint')
            ->filter()
            ->unique()
            ->values();

        if ($fingerprints->isEmpty()) {
            return $this->aggregateRisk->aggregateScores([]);
        }

        $lifecycles = FindingLifecycle::query()
            ->where('target_id', $assessment->target_id)
            ->whereIn('fingerprint', $fingerprints)
            ->get()
            ->keyBy('fingerprint');

        $scores = collect($findings)
            ->map(function (Finding $finding) use ($lifecycles) {
                $lifecycle = $lifecycles->get(
                    $finding->fingerprint
                );

                if ($lifecycle === null) {
                    return null;
                }

                return $this->riskScoring
                    ->score($lifecycle)['score'];
            })
            ->filter(fn ($score) => $score !== null)
            ->values();

        return $this->aggregateRisk->aggregateScores($scores);
    }

    private function findingChanges(
        Finding $previous,
        Finding $current
    ): array {
        $changes = [];

        foreach ([
            'severity',
            'confidence',
            'title',
            'type',
        ] as $field) {
            if ($previous->{$field} !== $current->{$field}) {
                $changes[$field] = [
                    'from' => $previous->{$field},
                    'to' => $current->{$field},
                ];
            }
        }

        return $changes;
    }

    private function serializeFinding(
        Finding $finding
    ): array {
        return [
            'id' => $finding->id,
            'fingerprint' => $finding->fingerprint,
            'type' => $finding->type,
            'title' => $finding->title,
            'severity' => $finding->severity,
            'confidence' => $finding->confidence,
            'status' => $finding->status,
        ];
    }

    private function serializeAssessment(
        Assessment $assessment
    ): array {
        return [
            'id' => $assessment->id,
            'target_id' => $assessment->target_id,
            'profile' => $assessment->profile,
            'status' => $assessment->status,
            'created_at' =>
                $assessment->created_at?->toISOString(),
            'completed_at' =>
                $assessment->completed_at?->toISOString(),
        ];
    }
}
