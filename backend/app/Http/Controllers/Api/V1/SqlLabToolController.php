<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Labs\SqlLabAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class SqlLabToolController extends Controller
{
    public function __construct(
        private readonly SqlLabAnalysisService $service
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool' => [
                'required',
                'string',
                'in:query-analyzer,schema-inspector,sql-formatter,data-profiler,security-analyzer,parameterization-coach,query-risk-report',
            ],
            'input' => [
                'required',
                'string',
                'max:' . SqlLabAnalysisService::MAX_INPUT_BYTES,
            ],
        ]);

        try {
            $started = microtime(true);

            $data = $this->service->analyze(
                $validated['tool'],
                $validated['input']
            );

            $data['duration_ms'] = (int) round(
                (microtime(true) - $started) * 1000
            );

            $data['checked_at'] = now()->toIso8601String();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'SQL Lab analysis failed.',
            ], 500);
        }
    }
}
