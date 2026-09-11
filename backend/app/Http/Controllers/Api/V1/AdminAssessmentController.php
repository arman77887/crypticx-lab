<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAssessmentController extends Controller
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

        $assessments = Assessment::query()
            ->with([
                'user:id,name,email',
                'target:id,user_id,name,url,hostname,authorization_confirmed,status',
            ])
            ->withCount('findings')
            ->latest()
            ->paginate($perPage);

        $assessments->through(function (Assessment $assessment) {
            return [
                'id' => $assessment->id,
                'user_id' => $assessment->user_id,
                'target_id' => $assessment->target_id,

                'profile' => $assessment->profile,
                'status' => $assessment->status,
                'progress' => (int) $assessment->progress,

                'worker_id' => $assessment->worker_id,

                'queued_at' => $assessment->queued_at,
                'started_at' => $assessment->started_at,
                'completed_at' => $assessment->completed_at,

                'error_message' => $assessment->error_message,

                'configuration' => $assessment->configuration,
                'execution_metadata' => $assessment->execution_metadata,

                'findings_count' => (int) $assessment->findings_count,

                'owner' => $assessment->user
                    ? [
                        'id' => $assessment->user->id,
                        'name' => $assessment->user->name,
                        'email' => $assessment->user->email,
                    ]
                    : null,

                'target' => $assessment->target
                    ? [
                        'id' => $assessment->target->id,
                        'name' => $assessment->target->name,
                        'url' => $assessment->target->url,
                        'hostname' => $assessment->target->hostname,
                        'authorization_confirmed' =>
                            (bool) $assessment->target->authorization_confirmed,
                        'status' => $assessment->target->status,
                    ]
                    : null,

                'created_at' => $assessment->created_at,
                'updated_at' => $assessment->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $assessments,
        ]);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', ['owner', 'administrator'])
            ->exists();
    }
}
