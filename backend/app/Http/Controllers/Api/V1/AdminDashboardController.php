<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\FindingLifecycle;
use App\Models\Target;
use App\Models\User;
use App\Services\AggregateRiskService;
use App\Services\RiskScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected RiskScoringService $riskScoring,
        protected AggregateRiskService $aggregateRisk,
    ) {
    }

    public function index(): JsonResponse
    {
        $lifecycles = FindingLifecycle::query()
            ->with('target')
            ->get();

        $currentLifecycles = $lifecycles
            ->whereIn('status', ['open', 'confirmed', 'reopened'])
            ->values();

        $scores = [];
        $riskDistribution = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'informational' => 0,
        ];

        foreach ($currentLifecycles as $lifecycle) {
            $risk = $this->riskScoring->score($lifecycle);

            $scores[] = $risk['score'];

            if (array_key_exists($risk['level'], $riskDistribution)) {
                $riskDistribution[$risk['level']]++;
            }
        }

        $overallRisk = $this->aggregateRisk->aggregateScores($scores);

        $assessmentCounts = [
            'total' => Assessment::query()->count(),
            'queued' => Assessment::query()
                ->where('status', 'queued')
                ->count(),
            'running' => Assessment::query()
                ->where('status', 'running')
                ->count(),
            'completed' => Assessment::query()
                ->where('status', 'completed')
                ->count(),
            'failed' => Assessment::query()
                ->where('status', 'failed')
                ->count(),
        ];

        $findingStatus = [
            'open' => $lifecycles->where('status', 'open')->count(),
            'confirmed' => $lifecycles->where('status', 'confirmed')->count(),
            'reopened' => $lifecycles->where('status', 'reopened')->count(),
            'resolved' => $lifecycles->where('status', 'resolved')->count(),
        ];

        $databaseStatus = $this->databaseStatus();

        $queueDriver = (string) config('queue.default', '');

        $queueStatus = $queueDriver !== ''
            ? sprintf('%s configured', ucfirst($queueDriver))
            : 'Not configured';

        /*
         * We do not pretend to know whether a queue:work process is alive.
         * This only reports workers actually referenced by currently-running
         * assessments.
         */
        $observedWorkers = Assessment::query()
            ->where('status', 'running')
            ->whereNotNull('worker_id')
            ->distinct()
            ->count('worker_id');

        $scannerWorkerStatus = $observedWorkers > 0
            ? sprintf(
                '%d active worker%s observed',
                $observedWorkers,
                $observedWorkers === 1 ? '' : 's'
            )
            : 'No active workers observed';

        $recentAssessments = Assessment::query()
            ->with('target:id,name,hostname')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Assessment $assessment) => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'progress' => $assessment->progress,
                'worker_id' => $assessment->worker_id,
                'target' => $assessment->target ? [
                    'id' => $assessment->target->id,
                    'name' => $assessment->target->name,
                    'hostname' => $assessment->target->hostname,
                ] : null,
                'created_at' => $assessment->created_at,
                'started_at' => $assessment->started_at,
                'completed_at' => $assessment->completed_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => User::query()->count(),
                    'verified' => User::query()
                        ->whereNotNull('email_verified_at')
                        ->count(),
                    'pending' => User::query()
                        ->whereNull('email_verified_at')
                        ->count(),
                ],

                'targets' => [
                    'total' => Target::query()->count(),
                    'active' => Target::query()
                        ->where('status', 'active')
                        ->count(),
                ],

                'assessments' => $assessmentCounts,

                'findings' => [
                    'total' => $lifecycles->count(),
                    'current' => $currentLifecycles->count(),
                    'open' => $findingStatus['open'],
                    'confirmed' => $findingStatus['confirmed'],
                    'reopened' => $findingStatus['reopened'],
                    'resolved' => $findingStatus['resolved'],
                    'critical' => $riskDistribution['critical'],
                    'high' => $riskDistribution['high'],
                    'risk_distribution' => $riskDistribution,
                    'critical_or_high' =>
                        $riskDistribution['critical'] +
                        $riskDistribution['high'],
                ],

                'overall_risk' => $overallRisk,

                'system' => [
                    // Reaching this endpoint proves the HTTP API responded.
                    'api' => 'Operational',
                    'database' => $databaseStatus,
                    'queue' => $queueStatus,
                    'scanner_workers' => $scannerWorkerStatus,
                ],

                'recent_assessments' => $recentAssessments,

                'scoring' => [
                    'version' => 'aggregate-risk-v1',
                    'formula' => '60% highest + 40% average',
                    'source' => 'all current platform finding lifecycles',
                ],
            ],
        ]);
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'Operational';
        } catch (Throwable) {
            return 'Unavailable';
        }
    }
}
