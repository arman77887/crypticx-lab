<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FindingResource;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Services\TelemetryService;
use App\Services\RiskScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FindingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Finding::query()
            ->with([
                'assessment:id,user_id,target_id,profile,status,progress,created_at,completed_at',
                'target:id,user_id,name,url,hostname',
            ])
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $user->id));

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('target_id')) {
            $query->where('target_id', $request->string('target_id'));
        }

        if ($request->filled('assessment_id')) {
            $query->where('assessment_id', $request->string('assessment_id'));
        }

        $allowedSorts = [
            'created_at',
            'severity',
            'status',
            'title',
        ];

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower($request->string('direction')->toString());

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $findings = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return FindingResource::collection($findings)->additional([
            'success' => true,
        ]);
    }

    public function show(Request $request, Finding $finding): FindingResource
    {
        $finding = Finding::query()
            ->with([
                'assessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
                'target:id,user_id,name,url,hostname,scheme,port,status',
            ])
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        return (new FindingResource($finding))->additional([
            'success' => true,
        ]);
    }

    public function lifecycle(Request $request, Finding $finding): JsonResponse
    {
        $finding = Finding::query()
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $lifecycle = FindingLifecycle::query()
            ->where('target_id', $finding->target_id)
            ->where('fingerprint', $finding->fingerprint)
            ->with([
                'firstAssessment:id,user_id,target_id,profile,status,created_at,completed_at',
                'lastAssessment:id,user_id,target_id,profile,status,created_at,completed_at',
                'lastFinding:id,assessment_id,target_id,type,fingerprint,title,severity,confidence,status,created_at',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'finding_id' => $finding->id,
                'fingerprint' => $finding->fingerprint,
                'lifecycle' => $lifecycle,
                'risk' => $lifecycle
                    ? app(RiskScoringService::class)->score($lifecycle)
                    : null,
            ],
        ]);
    }

    public function history(Request $request, Finding $finding): JsonResponse
    {
        $finding = Finding::query()
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $history = Finding::query()
            ->with([
                'assessment:id,user_id,target_id,profile,status,progress,created_at,completed_at',
            ])
            ->where('target_id', $finding->target_id)
            ->where('fingerprint', $finding->fingerprint)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'finding_id' => $finding->id,
                'fingerprint' => $finding->fingerprint,
                'history' => $history,
            ],
        ]);
    }

    public function assessments(Request $request, Finding $finding): JsonResponse
    {
        $finding = Finding::query()
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $assessments = Finding::query()
            ->where('target_id', $finding->target_id)
            ->where('fingerprint', $finding->fingerprint)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with([
                'assessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->pluck('assessment')
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'finding_id' => $finding->id,
                'fingerprint' => $finding->fingerprint,
                'assessment_count' => $assessments->count(),
                'assessments' => $assessments,
            ],
        ]);
    }

    public function evidence(Request $request, Finding $finding): JsonResponse
    {
        $finding = Finding::query()
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $occurrences = Finding::query()
            ->where('target_id', $finding->target_id)
            ->where('fingerprint', $finding->fingerprint)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('created_at')
            ->get([
                'id',
                'assessment_id',
                'evidence',
                'evidence_data',
                'status',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'finding_id' => $finding->id,
                'fingerprint' => $finding->fingerprint,
                'occurrence_count' => $occurrences->count(),
                'latest' => $occurrences->first(),
                'history' => $occurrences,
            ],
        ]);
    }

    public function remediation(Request $request, Finding $finding): JsonResponse
    {
        $finding = Finding::query()
            ->whereKey($finding->id)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
            ->firstOrFail();

        $latest = Finding::query()
            ->where('target_id', $finding->target_id)
            ->where('fingerprint', $finding->fingerprint)
            ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('created_at')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'finding_id' => $finding->id,
                'latest_finding_id' => $latest->id,
                'fingerprint' => $finding->fingerprint,
                'title' => $latest->title,
                'severity' => $latest->severity,
                'remediation' => $latest->remediation,
                'status' => $latest->status,
                'updated_at' => $latest->updated_at,
            ],
        ]);
    }


    public function updateStatus(Request $request, Finding $finding): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:confirmed,resolved'],
        ]);

        $requestedStatus = $validated['status'];

        $hasPermission = function (string $permission) use ($request): bool {
            return $request->user()
                ->roles()
                ->whereHas('permissions', function ($query) use ($permission) {
                    $query->where('slug', $permission);
                })
                ->exists();
        };

        if ($requestedStatus === 'confirmed') {
            if (! $hasPermission('findings.confirm')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'permission' => 'findings.confirm',
                ], 403);
            }

            return $this->confirm($request, $finding);
        }

        if ($requestedStatus === 'resolved') {
            if (! $hasPermission('findings.resolve')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.',
                    'permission' => 'findings.resolve',
                ], 403);
            }

            return $this->resolve($request, $finding);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unsupported finding status transition.',
        ], 422);
    }


    public function confirm(Request $request, Finding $finding): JsonResponse
    {
        return DB::transaction(function () use ($request, $finding): JsonResponse {
            $finding = Finding::query()
                ->whereKey($finding->id)
                ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
                ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
                ->lockForUpdate()
                ->firstOrFail();

            $lifecycle = FindingLifecycle::query()
                ->where('target_id', $finding->target_id)
                ->where('fingerprint', $finding->fingerprint)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lifecycle->status, ['open', 'reopened'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only an open or reopened finding can be confirmed.',
                    'current_status' => $lifecycle->status,
                ], 422);
            }

            $lifecycle->update([
                'status' => 'confirmed',
            ]);

            Finding::query()
                ->where('target_id', $finding->target_id)
                ->where('fingerprint', $finding->fingerprint)
                ->update([
                    'status' => 'confirmed',
                ]);

            app(TelemetryService::class)->audit(
                $request,
                'finding.confirmed',
                'finding',
                $request->user(),
                [
                    'success' => true,
                    'fingerprint' => $finding->fingerprint,
                ],
                'finding',
                $finding->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Finding confirmed.',
                'data' => [
                    'finding_id' => $finding->id,
                    'fingerprint' => $finding->fingerprint,
                    'lifecycle' => $lifecycle->fresh(),
                ],
            ]);
        });
    }

    public function resolve(Request $request, Finding $finding): JsonResponse
    {
        return DB::transaction(function () use ($request, $finding): JsonResponse {
            $finding = Finding::query()
                ->whereKey($finding->id)
                ->whereHas('assessment', fn ($q) => $q->where('user_id', $request->user()->id))
                ->whereHas('target', fn ($q) => $q->where('user_id', $request->user()->id))
                ->lockForUpdate()
                ->firstOrFail();

            $lifecycle = FindingLifecycle::query()
                ->where('target_id', $finding->target_id)
                ->where('fingerprint', $finding->fingerprint)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lifecycle->status !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only a confirmed finding can be resolved.',
                    'current_status' => $lifecycle->status,
                ], 422);
            }

            $lifecycle->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            Finding::query()
                ->where('target_id', $finding->target_id)
                ->where('fingerprint', $finding->fingerprint)
                ->update([
                    'status' => 'resolved',
                ]);

            app(TelemetryService::class)->audit(
                $request,
                'finding.resolved',
                'finding',
                $request->user(),
                [
                    'success' => true,
                    'fingerprint' => $finding->fingerprint,
                ],
                'finding',
                $finding->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Finding resolved.',
                'data' => [
                    'finding_id' => $finding->id,
                    'fingerprint' => $finding->fingerprint,
                    'lifecycle' => $lifecycle->fresh(),
                ],
            ]);
        });
    }


    public function lifecycleIndex(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = FindingLifecycle::query()
            ->with([
                'target:id,user_id,name,url,hostname',
                'lastFinding:id,assessment_id,target_id,type,fingerprint,title,severity,confidence,status,description,remediation,evidence_data,created_at',
            ])
            ->whereHas('target', fn ($q) => $q->where('user_id', $user->id));

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('target_id')) {
            $query->where('target_id', $request->string('target_id')->toString());
        }

        $allowedSorts = [
            'last_seen_at',
            'first_seen_at',
            'severity',
            'status',
            'title',
            'occurrence_count',
        ];

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'last_seen_at';
        }

        $direction = strtolower(
            $request->string('direction')->toString()
        );

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $lifecycles = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $riskScoring = app(RiskScoringService::class);

        $lifecycles->getCollection()->transform(function ($lifecycle) use ($riskScoring) {
            $lifecycle->setAttribute(
                'risk',
                $riskScoring->score($lifecycle)
            );

            return $lifecycle;
        });

        return response()->json([
            'success' => true,
            'data' => $lifecycles,
        ]);
    }

}
