<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\PlanQuotaException;
use App\Http\Requests\UpsertMonitoringPolicyRequest;
use App\Models\MonitoringPolicy;
use App\Models\Target;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $policies = MonitoringPolicy::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'target:id,name,url,hostname,status,authorization_confirmed',
                'lastAssessment:id,target_id,profile,status,queued_at,started_at,completed_at',
            ])
            ->orderByDesc('enabled')
            ->orderBy('next_run_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $policies,
        ]);
    }

    public function show(
        Request $request,
        Target $target,
    ): JsonResponse {
        if ($target->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $policy = MonitoringPolicy::query()
            ->where('user_id', $request->user()->id)
            ->where('target_id', $target->id)
            ->with([
                'target:id,name,url,hostname,status,authorization_confirmed',
                'lastAssessment:id,target_id,profile,status,queued_at,started_at,completed_at',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $policy,
        ]);
    }

    public function upsert(
        UpsertMonitoringPolicyRequest $request,
        Target $target,
        QuotaService $quotas,
    ): JsonResponse {
        if ($target->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validated();

        $existingPolicy = MonitoringPolicy::query()
            ->where('user_id', $request->user()->id)
            ->where('target_id', $target->id)
            ->first();

        try {
            $quotas->assertMonitoringAllowed(
                $request->user(),
                $existingPolicy?->id,
            );
        } catch (PlanQuotaException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        $result = DB::transaction(function () use (
            $request,
            $target,
            $validated,
        ) {
            /*
             * Serialize policy updates for this target.
             */
            $lockedTarget = Target::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = MonitoringPolicy::query()
                ->where('target_id', $lockedTarget->id)
                ->lockForUpdate()
                ->first();

            $enabled = array_key_exists('enabled', $validated)
                ? (bool) $validated['enabled']
                : ($existing?->enabled ?? true);

            /*
             * Autonomous scanning can only be enabled while the target
             * remains explicitly authorized and active.
             */
            if (
                $enabled &&
                (
                    ! $lockedTarget->authorization_confirmed ||
                    $lockedTarget->status !== 'active'
                )
            ) {
                return [
                    'error' => true,
                    'status' => 422,
                    'message' =>
                        'Monitoring cannot be enabled for an unauthorized or inactive target.',
                ];
            }

            $profile = $validated['profile']
                ?? $existing?->profile
                ?? 'standard';

            $interval = (int) (
                $validated['interval_minutes']
                ?? $existing?->interval_minutes
                ?? 1440
            );

            $configuration = array_key_exists(
                'configuration',
                $validated
            )
                ? ($validated['configuration'] ?? [])
                : ($existing?->configuration ?? []);

            /*
             * Enabling or materially changing the schedule starts a new
             * interval from now. This prevents an update from causing an
             * unexpected immediate autonomous scan.
             */
            $scheduleChanged =
                ! $existing ||
                ! $existing->enabled ||
                $existing->profile !== $profile ||
                (int) $existing->interval_minutes !== $interval ||
                ($existing->configuration ?? []) !== $configuration;

            if (! $enabled) {
                $nextRunAt = null;
            } elseif ($scheduleChanged) {
                $nextRunAt = now()->addMinutes($interval);
            } else {
                $nextRunAt = $existing->next_run_at
                    ?? now()->addMinutes($interval);
            }

            $policy = MonitoringPolicy::query()->updateOrCreate(
                [
                    'target_id' => $lockedTarget->id,
                ],
                [
                    'user_id' => $request->user()->id,
                    'enabled' => $enabled,
                    'profile' => $profile,
                    'interval_minutes' => $interval,
                    'configuration' => $configuration,
                    'next_run_at' => $nextRunAt,
                ],
            );

            return [
                'error' => false,
                'created' => ! $existing,
                'policy' => $policy->fresh()->load([
                    'target:id,name,url,hostname,status,authorization_confirmed',
                    'lastAssessment:id,target_id,profile,status,queued_at,started_at,completed_at',
                ]),
            ];
        });

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['created']
                ? 'Monitoring policy created successfully.'
                : 'Monitoring policy updated successfully.',
            'data' => $result['policy'],
        ], $result['created'] ? 201 : 200);
    }

    public function disable(
        Request $request,
        Target $target,
    ): JsonResponse {
        if ($target->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $policy = MonitoringPolicy::query()
            ->where('user_id', $request->user()->id)
            ->where('target_id', $target->id)
            ->first();

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => 'Monitoring policy not found.',
            ], 404);
        }

        $policy->update([
            'enabled' => false,
            'next_run_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Monitoring disabled successfully.',
            'data' => $policy->fresh(),
        ]);
    }
}
