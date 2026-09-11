<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Collection;

class AggregateRiskService
{
    public function __construct(
        protected RiskScoringService $riskScoring,
    ) {
    }

    /**
     * Aggregate multiple 0-100 risk scores.
     *
     * 60% highest score + 40% average score.
     */
    public function aggregateScores(Collection|array $scores): array
    {
        $scores = collect($scores)
            ->map(fn ($score) => max(0, min(100, (int) $score)))
            ->values();

        if ($scores->isEmpty()) {
            return [
                'score' => 0,
                'level' => 'informational',
                'highest' => 0,
                'average' => 0,
                'item_count' => 0,
            ];
        }

        $highest = (int) $scores->max();
        $average = round((float) $scores->avg(), 2);

        $score = (int) round(
            ($highest * 0.60) +
            ($average * 0.40)
        );

        return [
            'score' => $score,
            'level' => $this->level($score),
            'highest' => $highest,
            'average' => $average,
            'item_count' => $scores->count(),
        ];
    }

    public function user(User $user): array
    {
        $targets = Target::query()
            ->where('user_id', $user->id)
            ->get();

        $targetIds = $targets->pluck('id');

        $lifecycles = FindingLifecycle::query()
            ->with('target')
            ->whereIn('target_id', $targetIds)
            ->get();

        $scoresByLifecycleId = [];

        foreach ($lifecycles as $lifecycle) {
            $scoresByLifecycleId[$lifecycle->id] =
                $this->riskScoring->score($lifecycle);
        }

        $currentLifecycles = $lifecycles
            ->whereIn('status', ['open', 'confirmed', 'reopened'])
            ->values();

        $currentScores = $currentLifecycles
            ->map(
                fn ($lifecycle) =>
                    $scoresByLifecycleId[$lifecycle->id]['score']
            );

        $overall = $this->aggregateScores($currentScores);

        $targetRisk = $targets
            ->map(function (Target $target) use (
                $lifecycles,
                $scoresByLifecycleId
            ) {
                $targetLifecycles = $lifecycles
                    ->where('target_id', $target->id)
                    ->whereIn(
                        'status',
                        ['open', 'confirmed', 'reopened']
                    )
                    ->values();

                $scores = $targetLifecycles->map(
                    fn ($lifecycle) =>
                        $scoresByLifecycleId[$lifecycle->id]['score']
                );

                $aggregate = $this->aggregateScores($scores);

                return [
                    'target_id' => $target->id,
                    'name' => $target->name,
                    'hostname' => $target->hostname,
                    'status' => $target->status,
                    'risk_score' => $aggregate['score'],
                    'risk_level' => $aggregate['level'],
                    'finding_count' => $aggregate['item_count'],
                ];
            })
            ->sortByDesc('risk_score')
            ->values();

        $assessments = Assessment::query()
            ->where('user_id', $user->id)
            ->with('target:id,name,hostname')
            ->latest()
            ->limit(10)
            ->get();

        $assessmentIds = $assessments->pluck('id');

        $assessmentFindings = Finding::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->get([
                'id',
                'assessment_id',
                'target_id',
                'fingerprint',
            ]);

        $lifecycleLookup = $lifecycles
            ->keyBy(
                fn ($lifecycle) =>
                    $lifecycle->target_id . ':' .
                    $lifecycle->fingerprint
            );

        $assessmentRisk = $assessments->map(
            function (Assessment $assessment) use (
                $assessmentFindings,
                $lifecycleLookup,
                $scoresByLifecycleId
            ) {
                $scores = $assessmentFindings
                    ->where('assessment_id', $assessment->id)
                    ->map(function ($finding) use (
                        $lifecycleLookup,
                        $scoresByLifecycleId
                    ) {
                        $key = $finding->target_id . ':' .
                            $finding->fingerprint;

                        $lifecycle = $lifecycleLookup->get($key);

                        return $lifecycle
                            ? $scoresByLifecycleId[$lifecycle->id]['score']
                            : null;
                    })
                    ->filter(
                        fn ($score) => $score !== null
                    )
                    ->values();

                $aggregate = $this->aggregateScores($scores);

                return [
                    'assessment_id' => $assessment->id,
                    'target' => $assessment->target,
                    'status' => $assessment->status,
                    'progress' => $assessment->progress,
                    'risk_score' => $aggregate['score'],
                    'risk_level' => $aggregate['level'],
                    'finding_count' => $aggregate['item_count'],
                    'created_at' => $assessment->created_at,
                ];
            }
        );

        $statusCounts = [
            'open' => $lifecycles->where('status', 'open')->count(),
            'confirmed' => $lifecycles
                ->where('status', 'confirmed')
                ->count(),
            'reopened' => $lifecycles
                ->where('status', 'reopened')
                ->count(),
            'resolved' => $lifecycles
                ->where('status', 'resolved')
                ->count(),
        ];

        $riskDistribution = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'informational' => 0,
        ];

        foreach ($currentLifecycles as $lifecycle) {
            $level =
                $scoresByLifecycleId[$lifecycle->id]['level'];

            if (array_key_exists($level, $riskDistribution)) {
                $riskDistribution[$level]++;
            }
        }

        $assessmentStatusCounts = [
            'total' => Assessment::query()
                ->where('user_id', $user->id)
                ->count(),

            'queued' => Assessment::query()
                ->where('user_id', $user->id)
                ->where('status', 'queued')
                ->count(),

            'running' => Assessment::query()
                ->where('user_id', $user->id)
                ->where('status', 'running')
                ->count(),

            'completed' => Assessment::query()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),

            'failed' => Assessment::query()
                ->where('user_id', $user->id)
                ->where('status', 'failed')
                ->count(),
        ];

        return [
            'overall_risk' => $overall,

            'targets' => [
                'total' => $targets->count(),
                'active' => $targets
                    ->where('status', 'active')
                    ->count(),
                'risk' => $targetRisk,
            ],

            'assessments' => $assessmentStatusCounts,

            'findings' => [
                'total' => $lifecycles->count(),
                'current' => $currentLifecycles->count(),
                'status' => $statusCounts,
                'risk_distribution' => $riskDistribution,
                'critical_or_high' =>
                    $riskDistribution['critical'] +
                    $riskDistribution['high'],
            ],

            'recent_assessments' => $assessmentRisk,

            'scoring' => [
                'version' => 'aggregate-risk-v1',
                'formula' => '60% highest + 40% average',
                'source' => 'current finding lifecycle risk',
            ],
        ];
    }

    private function level(int $score): string
    {
        return match (true) {
            $score >= 85 => 'critical',
            $score >= 70 => 'high',
            $score >= 45 => 'medium',
            $score >= 20 => 'low',
            default => 'informational',
        };
    }
}
