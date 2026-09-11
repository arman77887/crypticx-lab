<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\WorkerNode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminWorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $queueDriver = (string) config('queue.default', '');
        $queueConfig = (array) config("queue.connections.{$queueDriver}", []);

        $queueMetrics = $this->queueMetrics(
            $queueDriver,
            $queueConfig
        );

        $failedMetrics = $this->failedJobMetrics();

        $workerRegistry = $this->workerRegistryMetrics();

        $runningAssessments = Assessment::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname',
            ])
            ->where('status', 'running')
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (Assessment $assessment) => [
                'assessment_id' => $assessment->id,
                'worker_id' => $assessment->worker_id,
                'profile' => $assessment->profile,
                'progress' => (int) $assessment->progress,
                'started_at' => $assessment->started_at,
                'queued_at' => $assessment->queued_at,

                'owner' => $assessment->user
                    ? [
                        'id' => $assessment->user->id,
                        'name' => $assessment->user->name,
                        'email' => $assessment->user->email,
                    ]
                    : null,

                'target' => $assessment->target
                    ? [
                        'id' => $assessment->target->id,
                        'name' => $assessment->target->name,
                        'url' => $assessment->target->url,
                        'hostname' => $assessment->target->hostname,
                    ]
                    : null,
            ])
            ->values();

        $queuedAssessments = Assessment::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname',
            ])
            ->where('status', 'queued')
            ->latest('queued_at')
            ->limit(25)
            ->get()
            ->map(fn (Assessment $assessment) => [
                'assessment_id' => $assessment->id,
                'profile' => $assessment->profile,
                'progress' => (int) $assessment->progress,
                'queued_at' => $assessment->queued_at,

                'owner' => $assessment->user
                    ? [
                        'id' => $assessment->user->id,
                        'name' => $assessment->user->name,
                        'email' => $assessment->user->email,
                    ]
                    : null,

                'target' => $assessment->target
                    ? [
                        'id' => $assessment->target->id,
                        'name' => $assessment->target->name,
                        'url' => $assessment->target->url,
                        'hostname' => $assessment->target->hostname,
                    ]
                    : null,
            ])
            ->values();

        $recentFailedAssessments = Assessment::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname',
            ])
            ->whereIn('status', ['failed', 'blocked'])
            ->latest('completed_at')
            ->limit(20)
            ->get()
            ->map(fn (Assessment $assessment) => [
                'assessment_id' => $assessment->id,
                'status' => $assessment->status,
                'profile' => $assessment->profile,
                'worker_id' => $assessment->worker_id,
                'error_message' => $assessment->error_message,
                'started_at' => $assessment->started_at,
                'completed_at' => $assessment->completed_at,

                'owner' => $assessment->user
                    ? [
                        'id' => $assessment->user->id,
                        'name' => $assessment->user->name,
                        'email' => $assessment->user->email,
                    ]
                    : null,

                'target' => $assessment->target
                    ? [
                        'id' => $assessment->target->id,
                        'name' => $assessment->target->name,
                        'url' => $assessment->target->url,
                        'hostname' => $assessment->target->hostname,
                    ]
                    : null,
            ])
            ->values();

        $observedExecutionIds = Assessment::query()
            ->where('status', 'running')
            ->whereNotNull('worker_id')
            ->distinct()
            ->count('worker_id');

        return response()->json([
            'success' => true,
            'data' => [
                'queue' => [
                    'driver' => $queueDriver !== ''
                        ? $queueDriver
                        : null,

                    'connection_status' => $queueDriver !== ''
                        ? 'configured'
                        : 'not_configured',

                    ...$queueMetrics,
                ],

                'failed_jobs' => $failedMetrics,

                'scanner' => [
                    'running_assessments' =>
                        $runningAssessments->count(),

                    'queued_assessments' =>
                        Assessment::query()
                            ->where('status', 'queued')
                            ->count(),

                    'observed_execution_ids' =>
                        $observedExecutionIds,

                    'worker_process_health' =>
                        $workerRegistry['summary_status'],

                    'worker_process_health_note' =>
                        'Health is derived from application-observed queue-worker heartbeats. It does not independently verify operating-system process state.',

                    'healthy_workers' =>
                        $workerRegistry['healthy'],

                    'stale_workers' =>
                        $workerRegistry['stale'],

                    'stopped_workers' =>
                        $workerRegistry['stopped'],
                ],

                'worker_registry' => $workerRegistry,

                'running_executions' => $runningAssessments,
                'queued_assessments' => $queuedAssessments,
                'recent_failed_assessments' =>
                    $recentFailedAssessments,

                'generated_at' => now(),
            ],
        ]);
    }

    private function workerRegistryMetrics(): array
    {
        $staleAfterSeconds = 45;
        $threshold = now()->subSeconds($staleAfterSeconds);

        try {
            $nodes = WorkerNode::query()
                ->latest('created_at')
                ->limit(100)
                ->get();

            $healthy = 0;
            $stale = 0;
            $stopped = 0;

            $mapped = $nodes
                ->map(function (WorkerNode $node) use (
                    $threshold,
                    &$healthy,
                    &$stale,
                    &$stopped
                ): array {
                    if ($node->status === 'stopped') {
                        $health = 'stopped';
                        $stopped++;
                    } elseif (
                        $node->status === 'running' &&
                        $node->last_heartbeat_at !== null &&
                        $node->last_heartbeat_at->gte($threshold)
                    ) {
                        $health = 'healthy';
                        $healthy++;
                    } else {
                        $health = 'stale';
                        $stale++;
                    }

                    $heartbeatAge = $node->last_heartbeat_at
                        ? max(
                            0,
                            (int) $node->last_heartbeat_at
                                ->diffInSeconds(now())
                        )
                        : null;

                    return [
                        'id' => $node->id,
                        'worker_type' => $node->worker_type,
                        'hostname' => $node->hostname,
                        'pid' => $node->pid,
                        'connection' => $node->connection,
                        'queue' => $node->queue,
                        'status' => $node->status,
                        'health' => $health,
                        'started_at' => $node->started_at,
                        'last_heartbeat_at' =>
                            $node->last_heartbeat_at,
                        'heartbeat_age_seconds' =>
                            $heartbeatAge,
                        'stopped_at' => $node->stopped_at,
                        'metadata' => $node->metadata,
                    ];
                })
                ->values();

            $summaryStatus = match (true) {
                $healthy > 0 && $stale === 0 =>
                    'healthy',

                $healthy > 0 && $stale > 0 =>
                    'degraded',

                $healthy === 0 && $stale > 0 =>
                    'stale',

                default =>
                    'no_active_workers',
            };

            return [
                'observable' => true,
                'semantics' =>
                    'application_heartbeat',
                'stale_after_seconds' =>
                    $staleAfterSeconds,
                'summary_status' =>
                    $summaryStatus,
                'healthy' => $healthy,
                'stale' => $stale,
                'stopped' => $stopped,
                'total' => $nodes->count(),
                'nodes' => $mapped,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'observable' => false,
                'semantics' =>
                    'application_heartbeat',
                'stale_after_seconds' =>
                    $staleAfterSeconds,
                'summary_status' =>
                    'unavailable',
                'healthy' => 0,
                'stale' => 0,
                'stopped' => 0,
                'total' => 0,
                'nodes' => [],
            ];
        }
    }

    private function queueMetrics(
        string $driver,
        array $config
    ): array {
        if ($driver !== 'database') {
            return [
                'depth_observable' => false,
                'queue_name' =>
                    $config['queue'] ?? null,
                'total_jobs' => null,
                'ready_jobs' => null,
                'reserved_jobs' => null,
                'delayed_jobs' => null,
                'oldest_job_at' => null,
                'note' =>
                    'Queue depth is not directly inspected by this endpoint for the configured non-database queue driver.',
            ];
        }

        $table = (string) ($config['table'] ?? 'jobs');
        $connection = $config['connection'] ?? null;

        try {
            $schema = $connection
                ? Schema::connection($connection)
                : Schema::getFacadeRoot();

            if (! $schema->hasTable($table)) {
                return [
                    'depth_observable' => false,
                    'queue_name' =>
                        $config['queue'] ?? null,
                    'total_jobs' => null,
                    'ready_jobs' => null,
                    'reserved_jobs' => null,
                    'delayed_jobs' => null,
                    'oldest_job_at' => null,
                    'note' =>
                        "Database queue table '{$table}' is unavailable.",
                ];
            }

            $db = $connection
                ? DB::connection($connection)
                : DB::connection();

            $now = now()->timestamp;

            $total = $db
                ->table($table)
                ->count();

            $reserved = $db
                ->table($table)
                ->whereNotNull('reserved_at')
                ->count();

            $ready = $db
                ->table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $now)
                ->count();

            $delayed = $db
                ->table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '>', $now)
                ->count();

            $oldestCreatedAt = $db
                ->table($table)
                ->min('created_at');

            return [
                'depth_observable' => true,
                'queue_name' =>
                    $config['queue'] ?? null,
                'total_jobs' => $total,
                'ready_jobs' => $ready,
                'reserved_jobs' => $reserved,
                'delayed_jobs' => $delayed,
                'oldest_job_at' => $oldestCreatedAt
                    ? Carbon::createFromTimestamp(
                        (int) $oldestCreatedAt
                    )->toIso8601String()
                    : null,
                'note' =>
                    'Database queue depth is read directly from the configured jobs table.',
            ];
        } catch (Throwable $e) {
            return [
                'depth_observable' => false,
                'queue_name' =>
                    $config['queue'] ?? null,
                'total_jobs' => null,
                'ready_jobs' => null,
                'reserved_jobs' => null,
                'delayed_jobs' => null,
                'oldest_job_at' => null,
                'note' =>
                    'Queue depth could not be inspected.',
            ];
        }
    }

    private function failedJobMetrics(): array
    {
        $failedConfig = (array) config('queue.failed', []);
        $driver = (string) ($failedConfig['driver'] ?? '');

        if (! in_array(
            $driver,
            ['database', 'database-uuids'],
            true
        )) {
            return [
                'driver' => $driver ?: null,
                'observable' => false,
                'total' => null,
                'recent' => [],
            ];
        }

        $connection = $failedConfig['database'] ?? null;
        $table = (string) (
            $failedConfig['table'] ?? 'failed_jobs'
        );

        try {
            $schema = $connection
                ? Schema::connection($connection)
                : Schema::getFacadeRoot();

            if (! $schema->hasTable($table)) {
                return [
                    'driver' => $driver,
                    'observable' => false,
                    'total' => null,
                    'recent' => [],
                ];
            }

            $db = $connection
                ? DB::connection($connection)
                : DB::connection();

            $total = $db
                ->table($table)
                ->count();

            $recent = $db
                ->table($table)
                ->select([
                    'id',
                    'uuid',
                    'connection',
                    'queue',
                    'failed_at',
                ])
                ->latest('failed_at')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'connection' => $row->connection,
                    'queue' => $row->queue,
                    'failed_at' => $row->failed_at,
                ])
                ->values();

            return [
                'driver' => $driver,
                'observable' => true,
                'total' => $total,
                'recent' => $recent,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => $driver,
                'observable' => false,
                'total' => null,
                'recent' => [],
            ];
        }
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', [
                'owner',
                'administrator',
            ])
            ->exists();
    }
}
