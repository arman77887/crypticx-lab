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
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'scan_type' => [
                'nullable',
                'string',
                'in:web_security,ssl_tls,dns_intelligence,api_security',
            ],
            'status' => [
                'nullable',
                'string',
                'in:queued,running,completed,failed',
            ],
            'severity' => [
                'nullable',
                'string',
                'in:critical,high,medium,low,info,informational',
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Assessment::query()
            ->with('target:id,name,url,hostname')
            ->withCount([
                'findings',
                'findings as critical_findings_count' => fn ($query) =>
                    $query->where('severity', 'critical'),
                'findings as high_findings_count' => fn ($query) =>
                    $query->where('severity', 'high'),
                'findings as medium_findings_count' => fn ($query) =>
                    $query->where('severity', 'medium'),
                'findings as low_findings_count' => fn ($query) =>
                    $query->where('severity', 'low'),
                'findings as informational_findings_count' => fn ($query) =>
                    $query->whereIn('severity', ['info', 'informational']),
            ])
            ->where('user_id', $request->user()->id);

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);

            $query->where(function ($query) use ($search) {
                $query
                    ->where('id', 'like', '%' . $search . '%')
                    ->orWhereHas('target', function ($targetQuery) use ($search) {
                        $targetQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('url', 'like', '%' . $search . '%')
                            ->orWhere('hostname', 'like', '%' . $search . '%');
                    });
            });
        }

        if (! empty($validated['scan_type'])) {
            $query->where(
                'configuration->scan_type',
                $validated['scan_type']
            );
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['severity'])) {
            $severity = $validated['severity'];

            $query->whereHas('findings', function ($findingQuery) use ($severity) {
                if (in_array($severity, ['info', 'informational'], true)) {
                    $findingQuery->whereIn(
                        'severity',
                        ['info', 'informational']
                    );

                    return;
                }

                $findingQuery->where('severity', $severity);
            });
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $assessments = $query
            ->latest('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

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
