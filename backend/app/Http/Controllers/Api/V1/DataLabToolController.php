<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Labs\DataLabAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class DataLabToolController extends Controller
{
    public function __construct(
        private readonly DataLabAnalysisService $service
    ) {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool' => [
                'required',
                'string',
                'in:csv-analyzer,json-inspector,data-cleaner,pattern-analysis,log-analyzer,http-inspector,encoding-studio,hash-inspector,jwt-inspector,regex-lab,data-diff,sensitive-data-redactor',
            ],
            'input' => [
                'required',
                'string',
                'max:' . DataLabAnalysisService::MAX_INPUT_BYTES,
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
                'message' => 'Data Lab analysis failed.',
            ], 500);
        }
    }
}
