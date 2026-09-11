<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\PlanQuotaException;
use App\Models\Assessment;
use App\Models\Report;
use App\Services\ReportGenerationService;
use App\Services\ReportPdfService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'target:id,name,url,hostname',
                'assessment:id,target_id,profile,status,completed_at',
            ])
            ->latest('generated_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function show(
        Request $request,
        Report $report
    ): JsonResponse {
        if ($report->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $report->load([
            'target:id,name,url,hostname',
            'assessment:id,target_id,profile,status,completed_at',
        ]);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function pdf(
        Request $request,
        Report $report,
        ReportPdfService $pdf
    ) {
        if ($report->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $pdf->download($report);
    }

    public function store(
        Request $request,
        Assessment $assessment,
        ReportGenerationService $reports
    ): JsonResponse {
        try {
            $report = $reports->generate(
                $request->user(),
                $assessment
            );
        } catch (PlanQuotaException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        $report->load([
            'target:id,name,url,hostname',
            'assessment:id,target_id,profile,status,completed_at',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully.',
            'data' => $report,
        ], 201);
    }
}
