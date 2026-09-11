<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\PlanQuotaException;
use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assessments = Assessment::query()
            ->with('target:id,name,url,hostname')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $assessments,
        ]);
    }

    public function show(Request $request, Assessment $assessment): JsonResponse
    {
        if ($assessment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $assessment->load([
            'target:id,name,url,hostname',
            'findings',
        ]);

        return response()->json([
            'success' => true,
            'data' => $assessment,
        ]);
    }

    public function intelligence(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        if ($assessment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($assessment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Assessment intelligence is available only for completed assessments.',
            ], 422);
        }

        $intelligence = app(
            \App\Services\AssessmentIntelligenceService::class
        )->compareWithPrevious($assessment);

        return response()->json([
            'success' => true,
            'data' => $intelligence,
        ]);
    }

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        $target = \App\Models\Target::query()
            ->where('id', $request->validated('target_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $assessment = app(
                \App\Services\AssessmentDispatchService::class
            )->dispatchManual(
                $request->user(),
                $target,
                $request->validated('profile'),
                $request->validated('configuration') ?? [],
            );
        } catch (PlanQuotaException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        } catch (\RuntimeException $e) {
            $status = str_contains(
                $e->getMessage(),
                'platform policy'
            ) ? 403 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assessment queued successfully.',
            'data' => $assessment->fresh()->load('target'),
        ], 202);
    }
}
