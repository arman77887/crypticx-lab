<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Services\Scanner\NetworkAnalysisService;
use App\Services\Security\AuthorizedTargetGuard;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class NetworkAnalysisToolController extends Controller
{
    public function __construct(
        private readonly AuthorizedTargetGuard $targetGuard,
        private readonly NetworkAnalysisService $analysisService
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => [
                'required',
                'uuid',
            ],
            'tool' => [
                'required',
                'string',
                'in:port-analysis,service-discovery,network-inspector,exposure-review',
            ],
        ]);

        try {
            $target = Target::query()
                ->whereKey($validated['target_id'])
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->first();

            if (!$target) {
                throw new RuntimeException(
                    'Authorized target was not found.'
                );
            }

            $this->targetGuard->assertAccessible(
                $request->user(),
                $target
            );

            $data = $this->analysisService->analyze(
                $target,
                $validated['tool']
            );

            $data['target_id'] = (string) $target->id;
            $data['checked_at'] =
                now()->toIso8601String();

            app(TelemetryService::class)->audit(
                $request,
                'tool.network_analysis',
                'security_tool',
                $request->user(),
                [
                    'tool' => $validated['tool'],
                    'hostname' =>
                        (string) $target->hostname,
                    'status' => 'completed',
                    'duration_ms' =>
                        $data['duration_ms'] ?? null,
                ],
                'target',
                $target->id,
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Network analysis failed.',
            ], 502);
        }
    }
}
