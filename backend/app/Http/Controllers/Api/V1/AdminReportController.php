<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $perPage = min(
            max((int) $request->integer('per_page', 50), 1),
            100
        );

        $reports = Report::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname,status,authorization_confirmed',
                'assessment:id,user_id,target_id,profile,status,completed_at',
            ])
            ->latest('generated_at')
            ->paginate($perPage)
            ->withQueryString();

        $reports->through(
            fn (Report $report): array => $this->serializeReport($report)
        );

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function show(
        Request $request,
        Report $report
    ): JsonResponse {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $report->load([
            'user:id,name,email',
            'target:id,user_id,name,url,hostname,status,authorization_confirmed',
            'assessment:id,user_id,target_id,profile,status,completed_at',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeReport($report),
        ]);
    }

    public function pdf(
        Request $request,
        Report $report,
        ReportPdfService $pdf
    ) {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return $pdf->download($report);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', ['owner', 'administrator'])
            ->exists();
    }

    private function serializeReport(Report $report): array
    {
        $findings = is_array($report->findings_snapshot)
            ? $report->findings_snapshot
            : [];

        $risk = is_array($report->risk_snapshot)
            ? $report->risk_snapshot
            : [];

        $metadata = is_array($report->metadata)
            ? $report->metadata
            : [];

        return [
            'id' => $report->id,
            'user_id' => $report->user_id,
            'target_id' => $report->target_id,
            'assessment_id' => $report->assessment_id,
            'title' => $report->title,
            'status' => $report->status,

            'owner' => $report->user
                ? [
                    'id' => $report->user->id,
                    'name' => $report->user->name,
                    'email' => $report->user->email,
                ]
                : null,

            'target' => $report->target
                ? [
                    'id' => $report->target->id,
                    'name' => $report->target->name,
                    'url' => $report->target->url,
                    'hostname' => $report->target->hostname,
                    'status' => $report->target->status,
                    'authorization_confirmed' =>
                        (bool) $report->target->authorization_confirmed,
                ]
                : null,

            'assessment' => $report->assessment
                ? [
                    'id' => $report->assessment->id,
                    'profile' => $report->assessment->profile,
                    'status' => $report->assessment->status,
                    'completed_at' => $report->assessment->completed_at,
                ]
                : null,

            /*
             * These are persisted report snapshots.
             * They are intentionally returned from the report row rather
             * than recalculated from current lifecycle state.
             */
            'target_snapshot' => $report->target_snapshot,
            'assessment_snapshot' => $report->assessment_snapshot,
            'findings_snapshot' => $findings,
            'risk_snapshot' => $risk,
            'intelligence_snapshot' => $report->intelligence_snapshot,
            'metadata' => $metadata,

            'summary' => [
                'findings_count' => count($findings),
                'risk_score' => isset($risk['score'])
                    ? (int) $risk['score']
                    : null,
                'risk_level' => $risk['level'] ?? null,
                'immutable_snapshot' =>
                    ($metadata['immutable_snapshot'] ?? false) === true,
            ],

            'generated_at' => $report->generated_at,
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
        ];
    }
}
