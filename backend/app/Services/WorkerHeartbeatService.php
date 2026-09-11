<?php

namespace App\Services;

use App\Models\WorkerNode;
use Throwable;

class WorkerHeartbeatService
{
    private const WRITE_INTERVAL_SECONDS = 10;

    private static ?string $workerNodeId = null;

    public function boot(
        ?string $connection = null,
        ?string $queue = null
    ): void {
        if (self::$workerNodeId !== null) {
            return;
        }

        $now = now();

        $worker = WorkerNode::query()->create([
            'worker_type' => 'queue',
            'hostname' => gethostname() ?: null,
            'pid' => getmypid() ?: null,
            'connection' => $connection,
            'queue' => $queue,
            'status' => 'running',
            'started_at' => $now,
            'last_heartbeat_at' => $now,
            'stopped_at' => null,
            'metadata' => [
                'source' => 'laravel_queue_worker',
                'state' => 'booted',
            ],
        ]);

        self::$workerNodeId = (string) $worker->getKey();
    }

    public function beat(
        ?string $connection = null,
        ?string $queue = null,
        array $metadata = [],
        bool $force = false
    ): void {
        try {
            $this->boot($connection, $queue);

            $query = WorkerNode::query()
                ->whereKey(self::$workerNodeId);

            if (! $force) {
                $query->where(function ($q): void {
                    $q->whereNull('last_heartbeat_at')
                        ->orWhere(
                            'last_heartbeat_at',
                            '<=',
                            now()->subSeconds(
                                self::WRITE_INTERVAL_SECONDS
                            )
                        );
                });
            }

            $query->update([
                'connection' => $connection,
                'queue' => $queue,
                'status' => 'running',
                'last_heartbeat_at' => now(),
                'stopped_at' => null,
                'metadata' => array_merge(
                    [
                        'source' => 'laravel_queue_worker',
                    ],
                    $metadata
                ),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            /*
             * Observability failure must never terminate queue execution.
             */
            report($e);
        }
    }

    public function stop(array $metadata = []): void
    {
        if (self::$workerNodeId === null) {
            return;
        }

        try {
            WorkerNode::query()
                ->whereKey(self::$workerNodeId)
                ->update([
                    'status' => 'stopped',
                    'last_heartbeat_at' => now(),
                    'stopped_at' => now(),
                    'metadata' => array_merge(
                        [
                            'source' => 'laravel_queue_worker',
                        ],
                        $metadata
                    ),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function currentWorkerNodeId(): ?string
    {
        return self::$workerNodeId;
    }
}
