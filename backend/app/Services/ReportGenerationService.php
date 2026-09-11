<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\FindingLifecycle;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportGenerationService
{
    public function __construct(
        protected RiskScoringService $riskScoring,
        protected AggregateRiskService $aggregateRisk,
        protected AssessmentIntelligenceService $assessmentIntelligence,
        protected QuotaService $quotas,
    ) {
    }

    public function generate(User $user, Assessment $assessment): Report
    {
        if ($assessment->user_id !== $user->id) {
            throw new AuthorizationException(
                'You do not own this assessment.'
            );
        }

        $this->quotas->assertReportGenerationAllowed(
            $user,
        );

        if ($assessment->status !== 'completed') {
            throw ValidationException::withMessages([
                'assessment' =>
                    'Reports can only be generated from completed assessments.',
            ]);
        }

        $assessment->loadMissing([
            'target',
            'findings',
        ]);

        if (!$assessment->target) {
            throw ValidationException::withMessages([
                'target' => 'Assessment target is unavailable.',
            ]);
        }

        /*
         * Ownership and target identity are derived from the assessment.
         * They are never accepted from client-supplied report fields.
         */
        if (
            $assessment->target_id !== $assessment->target->id ||
            $assessment->target->user_id !== $user->id
        ) {
            throw new AuthorizationException(
                'Assessment target ownership is inconsistent.'
            );
        }

        $generatedAt = now();

        $targetSnapshot = $this->targetSnapshot($assessment);
        $assessmentSnapshot = $this->assessmentSnapshot($assessment);
        $findingsSnapshot = $this->findingsSnapshot($assessment);

        $riskSnapshot = $this->riskSnapshot($assessment);

        /*
         * Assessment Intelligence intentionally derives historical comparison
         * risk from current lifecycle scoring. Once copied into this report,
         * however, the resulting payload becomes an immutable report snapshot.
         */
        $intelligence = $this->assessmentIntelligence
            ->compareWithPrevious($assessment);

        $metadata = [
            'schema_version' => 'report-v1',
            'generated_at' => $generatedAt->toIso8601String(),
            'immutable_snapshot' => true,
            'source' => 'completed_assessment',
            'risk_source' => 'current_lifecycle_scoring_at_generation',
            'risk_probability' => false,
            'intelligence_historical_snapshot' => false,
        ];

        return DB::transaction(function () use (
            $assessment,
            $user,
            $generatedAt,
            $targetSnapshot,
            $assessmentSnapshot,
            $findingsSnapshot,
            $riskSnapshot,
            $intelligence,
            $metadata,
        ): Report {
            return Report::create([
                'user_id' => $user->id,
                'target_id' => $assessment->target_id,
                'assessment_id' => $assessment->id,

                'title' => $this->reportTitle($assessment),
                'status' => 'ready',

                'target_snapshot' => $targetSnapshot,
                'assessment_snapshot' => $assessmentSnapshot,
                'findings_snapshot' => $findingsSnapshot,
                'risk_snapshot' => $riskSnapshot,
                'intelligence_snapshot' => $intelligence,
                'metadata' => $metadata,

                'generated_at' => $generatedAt,
            ]);
        });
    }

    private function reportTitle(Assessment $assessment): string
    {
        $targetName = trim(
            (string) ($assessment->target->name ?: $assessment->target->hostname)
        );

        if ($targetName === '') {
            $targetName = 'Security Assessment';
        }

        return mb_substr(
            $targetName . ' Security Assessment Report',
            0,
            255
        );
    }

    private function targetSnapshot(Assessment $assessment): array
    {
        $target = $assessment->target;

        return [
            'id' => $target->id,
            'name' => $target->name,
            'url' => $target->url,
            'hostname' => $target->hostname,
            'scheme' => $target->scheme,
            'port' => $target->port,
            'status' => $target->status,

            'authorization' => [
                'confirmed' => (bool) $target->authorization_confirmed,
                'confirmed_at' =>
                    $target->authorization_confirmed_at?->toIso8601String(),
                'method' => $target->authorization_method,
            ],
        ];
    }

    private function assessmentSnapshot(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'target_id' => $assessment->target_id,
            'profile' => $assessment->profile,
            'status' => $assessment->status,
            'progress' => $assessment->progress,

            'queued_at' =>
                $assessment->queued_at?->toIso8601String(),
            'started_at' =>
                $assessment->started_at?->toIso8601String(),
            'completed_at' =>
                $assessment->completed_at?->toIso8601String(),

            'configuration' => $assessment->configuration,
            'execution_metadata' => $assessment->execution_metadata,

            'finding_count' => $assessment->findings->count(),
        ];
    }

    private function findingsSnapshot(Assessment $assessment): array
    {
        return $assessment->findings
            ->map(function ($finding): array {
                return [
                    'id' => $finding->id,
                    'fingerprint' => $finding->fingerprint,
                    'type' => $finding->type,
                    'title' => $finding->title,
                    'description' => $finding->description,
                    'severity' => $finding->severity,
                    'confidence' => $finding->confidence,
                    'evidence' => $finding->evidence,
                    'evidence_data' => $finding->evidence_data,
                    'remediation' => $finding->remediation,
                    'status' => $finding->status,
                ];
            })
            ->values()
            ->all();
    }

    private function riskSnapshot(Assessment $assessment): array
    {
        $fingerprints = $assessment->findings
            ->pluck('fingerprint')
            ->filter()
            ->unique()
            ->values();

        if ($fingerprints->isEmpty()) {
            $aggregate = $this->aggregateRisk->aggregateScores([]);

            return [
                ...$aggregate,
                'scoring' => [
                    'source' => 'current_lifecycle_scoring_at_generation',
                    'probability' => false,
                    'immutable_snapshot' => true,
                ],
            ];
        }

        $lifecycles = FindingLifecycle::query()
            ->where('target_id', $assessment->target_id)
            ->whereIn('fingerprint', $fingerprints)
            ->get()
            ->keyBy('fingerprint');

        $scores = $assessment->findings
            ->map(function ($finding) use ($lifecycles) {
                $lifecycle = $lifecycles->get($finding->fingerprint);

                if (!$lifecycle) {
                    return null;
                }

                return $this->riskScoring->score($lifecycle);
            })
            ->filter()
            ->values();

        $aggregate = $this->aggregateRisk->aggregateScores($scores);

        return [
            ...$aggregate,

            'scoring' => [
                'source' => 'current_lifecycle_scoring_at_generation',
                'probability' => false,
                'immutable_snapshot' => true,
                'finding_count' => $assessment->findings->count(),
                'scored_lifecycle_count' => $scores->count(),
            ],
        ];
    }
}
