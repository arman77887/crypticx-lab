<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Services\Scanner\ApiSecurityInspectionService;
use App\Services\Security\AuthorizedTargetGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class ApiSecurityToolController extends Controller
{
    public function __construct(
        private readonly AuthorizedTargetGuard $targetGuard,
        private readonly ApiSecurityInspectionService $inspectionService,
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => [
                'required',
                'uuid',
            ],
        ]);

        try {
            /*
             * Do not use an unscoped exists rule here. Ownership
             * filtering prevents cross-user target enumeration.
             */
            $target = Target::query()
                ->whereKey($validated['target_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $target) {
                throw new RuntimeException(
                    'Authorized target was not found.'
                );
            }

            $this->targetGuard->assertAccessible(
                $request->user(),
                $target
            );

            $result = $this->inspectionService->inspect(
                $target
            );

            $result['checked_at'] =
                now()->toIso8601String();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The API endpoint could not be analyzed.',
            ], 502);
        }
    }
}
