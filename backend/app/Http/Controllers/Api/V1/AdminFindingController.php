<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Services\RiskScoringService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminFindingController extends Controller
{
    public function index(
        Request $request,
        RiskScoringService $riskScoring
    ): JsonResponse {
        if (! $this->isPlatformAdmin($request)) {
            return $this->forbidden();
        }

        $perPage = min(
            max((int) $request->integer('per_page', 50), 1),
            100
        );

        $query = FindingLifecycle::query()
            ->with([
                'target:id,user_id,name,url,hostname,status,authorization_confirmed,metadata',
                'target.user:id,name,email',
                'firstAssessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
                'lastAssessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
                'lastFinding:id,assessment_id,target_id,type,fingerprint,title,description,severity,confidence,evidence,evidence_data,remediation,status,created_at,updated_at',
            ]);

        if ($request->filled('severity')) {
            $query->where(
                'severity',
                $request->string('severity')->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->string('type')->toString()
            );
        }

        if ($request->filled('target_id')) {
            $query->where(
                'target_id',
                $request->string('target_id')->toString()
            );
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

        $lifecycles = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $lifecycles->through(
            function (FindingLifecycle $lifecycle) use ($riskScoring) {
                return $this->serializeLifecycle(
                    $lifecycle,
                    $riskScoring
                );
            }
        );

        return response()->json([
            'success' => true,
            'data' => $lifecycles,
        ]);
    }

    public function updateStatus(
        Request $request,
        FindingLifecycle $lifecycle,
        RiskScoringService $riskScoring
    ): JsonResponse {
        if (! $this->isPlatformAdmin($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:confirmed,resolved',
            ],
        ]);

        $requestedStatus = $validated['status'];

        $requiredPermission = $requestedStatus === 'confirmed'
            ? 'findings.confirm'
            : 'findings.resolve';

        if (! $this->hasPermission($request, $requiredPermission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'permission' => $requiredPermission,
            ], 403);
        }

        return DB::transaction(
            function () use (
                $request,
                $lifecycle,
                $requestedStatus,
                $riskScoring
            ): JsonResponse {
                $lifecycle = FindingLifecycle::query()
                    ->whereKey($lifecycle->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $requestedStatus === 'confirmed'
                    && ! in_array(
                        $lifecycle->status,
                        ['open', 'reopened'],
                        true
                    )
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Only an open or reopened finding can be confirmed.',
                        'current_status' => $lifecycle->status,
                    ], 422);
                }

                if (
                    $requestedStatus === 'resolved'
                    && $lifecycle->status !== 'confirmed'
                ) {
                    return response()->json([
                        'success' => false,
                        'message' =>
                            'Only a confirmed finding can be resolved.',
                        'current_status' => $lifecycle->status,
                    ], 422);
                }

                $updates = [
                    'status' => $requestedStatus,
                ];

                if ($requestedStatus === 'confirmed') {
                    $updates['resolved_at'] = null;
                }

                if ($requestedStatus === 'resolved') {
                    $updates['resolved_at'] = now();
                }

                $lifecycle->update($updates);

                Finding::query()
                    ->where('target_id', $lifecycle->target_id)
                    ->where('fingerprint', $lifecycle->fingerprint)
                    ->update([
                        'status' => $requestedStatus,
                    ]);

                app(TelemetryService::class)->audit(
                    $request,
                    'admin.finding.'.$requestedStatus,
                    'finding_lifecycle',
                    $request->user(),
                    [
                        'success' => true,
                        'fingerprint' => $lifecycle->fingerprint,
                        'target_id' => $lifecycle->target_id,
                        'status' => $requestedStatus,
                    ],
                    'finding_lifecycle',
                    $lifecycle->id,
                );

                $lifecycle->load([
                    'target:id,user_id,name,url,hostname,status,authorization_confirmed,metadata',
                    'target.user:id,name,email',
                    'firstAssessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
                    'lastAssessment:id,user_id,target_id,profile,status,progress,queued_at,started_at,completed_at,worker_id,created_at',
                    'lastFinding:id,assessment_id,target_id,type,fingerprint,title,description,severity,confidence,evidence,evidence_data,remediation,status,created_at,updated_at',
                ]);

                return response()->json([
                    'success' => true,
                    'message' =>
                        $requestedStatus === 'confirmed'
                            ? 'Finding confirmed.'
                            : 'Finding resolved.',
                    'data' => $this->serializeLifecycle(
                        $lifecycle,
                        $riskScoring
                    ),
                ]);
            }
        );
    }

    private function serializeLifecycle(
        FindingLifecycle $lifecycle,
        RiskScoringService $riskScoring
    ): array {
        $target = $lifecycle->target;
        $owner = $target?->user;
        $latest = $lifecycle->lastFinding;

        return [
            'id' => $lifecycle->id,
            'target_id' => $lifecycle->target_id,
            'fingerprint' => $lifecycle->fingerprint,

            'type' => $lifecycle->type,
            'title' => $lifecycle->title,
            'severity' => $lifecycle->severity,
            'confidence' => $lifecycle->confidence,
            'status' => $lifecycle->status,

            'occurrence_count' =>
                (int) $lifecycle->occurrence_count,

            'first_seen_at' => $lifecycle->first_seen_at,
            'last_seen_at' => $lifecycle->last_seen_at,
            'resolved_at' => $lifecycle->resolved_at,
            'reopened_at' => $lifecycle->reopened_at,

            'risk' => $riskScoring->score($lifecycle),

            'owner' => $owner
                ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ]
                : null,

            'target' => $target
                ? [
                    'id' => $target->id,
                    'name' => $target->name,
                    'url' => $target->url,
                    'hostname' => $target->hostname,
                    'status' => $target->status,
                    'authorization_confirmed' =>
                        (bool) $target->authorization_confirmed,
                ]
                : null,

            'first_assessment' => $lifecycle->firstAssessment
                ? [
                    'id' => $lifecycle->firstAssessment->id,
                    'profile' =>
                        $lifecycle->firstAssessment->profile,
                    'status' =>
                        $lifecycle->firstAssessment->status,
                    'created_at' =>
                        $lifecycle->firstAssessment->created_at,
                    'completed_at' =>
                        $lifecycle->firstAssessment->completed_at,
                ]
                : null,

            'last_assessment' => $lifecycle->lastAssessment
                ? [
                    'id' => $lifecycle->lastAssessment->id,
                    'profile' =>
                        $lifecycle->lastAssessment->profile,
                    'status' =>
                        $lifecycle->lastAssessment->status,
                    'progress' =>
                        (int) $lifecycle->lastAssessment->progress,
                    'worker_id' =>
                        $lifecycle->lastAssessment->worker_id,
                    'created_at' =>
                        $lifecycle->lastAssessment->created_at,
                    'completed_at' =>
                        $lifecycle->lastAssessment->completed_at,
                ]
                : null,

            'latest_finding' => $latest
                ? [
                    'id' => $latest->id,
                    'assessment_id' => $latest->assessment_id,
                    'description' => $latest->description,
                    'evidence' => $latest->evidence,
                    'evidence_data' => $latest->evidence_data,
                    'remediation' => $latest->remediation,
                    'created_at' => $latest->created_at,
                    'updated_at' => $latest->updated_at,
                ]
                : null,

            'created_at' => $lifecycle->created_at,
            'updated_at' => $lifecycle->updated_at,
        ];
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', ['owner', 'administrator'])
            ->exists();
    }

    private function hasPermission(
        Request $request,
        string $permission
    ): bool {
        return $request->user()
            ->roles()
            ->whereHas(
                'permissions',
                fn ($query) =>
                    $query->where('slug', $permission)
            )
            ->exists();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
        ], 403);
    }
}
