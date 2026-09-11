<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Target;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $targets = Target::query()
            ->with([
                'user:id,name,email',
            ])
            ->withCount([
                'assessments',
                'findings',
            ])
            ->withMax(
                'assessments as last_assessment_at',
                'created_at'
            )
            ->latest()
            ->paginate(
                min(
                    max((int) $request->integer('per_page', 50), 1),
                    100
                )
            );

        $targets->through(function (Target $target) {
            return [
                'id' => $target->id,
                'user_id' => $target->user_id,

                'name' => $target->name,
                'url' => $target->url,
                'hostname' => $target->hostname,
                'scheme' => $target->scheme,
                'port' => $target->port,

                'authorization_confirmed' =>
                    (bool) $target->authorization_confirmed,

                'authorization_confirmed_at' =>
                    $target->authorization_confirmed_at,

                'authorization_method' =>
                    $target->authorization_method,

                'status' => $target->status,

                'owner' => $target->user
                    ? [
                        'id' => $target->user->id,
                        'name' => $target->user->name,
                        'email' => $target->user->email,
                    ]
                    : null,

                'assessments_count' =>
                    (int) $target->assessments_count,

                'findings_count' =>
                    (int) $target->findings_count,

                'last_assessment_at' =>
                    $target->last_assessment_at,

                'created_at' => $target->created_at,
                'updated_at' => $target->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $targets,
        ]);
    }

    public function update(
        Request $request,
        Target $target
    ): JsonResponse {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:160',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'active',
                    'paused',
                ]),
            ],

            'authorization_confirmed' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ]);

        if (
            array_key_exists(
                'authorization_confirmed',
                $data
            )
        ) {
            $confirmed =
                (bool) $data['authorization_confirmed'];

            if ($confirmed) {
                if (!$target->authorization_confirmed) {
                    $data['authorization_confirmed_at'] = now();
                }
            } else {
                $data['authorization_confirmed_at'] = null;

                /*
                 * A target without confirmed authorization
                 * must never remain eligible for assessment.
                 */
                $data['status'] = 'paused';
            }
        }

        $target->update($data);

        $target->load(
            'user:id,name,email'
        );

        $target->loadCount([
            'assessments',
            'findings',
        ]);

        $lastAssessmentAt =
            $target->assessments()
                ->max('created_at');

        return response()->json([
            'success' => true,
            'message' =>
                'Target governance state updated successfully.',

            'data' => [
                'id' => $target->id,
                'user_id' => $target->user_id,

                'name' => $target->name,
                'url' => $target->url,
                'hostname' => $target->hostname,
                'scheme' => $target->scheme,
                'port' => $target->port,

                'authorization_confirmed' =>
                    (bool) $target->authorization_confirmed,

                'authorization_confirmed_at' =>
                    $target->authorization_confirmed_at,

                'authorization_method' =>
                    $target->authorization_method,

                'status' => $target->status,

                'owner' => $target->user
                    ? [
                        'id' => $target->user->id,
                        'name' => $target->user->name,
                        'email' => $target->user->email,
                    ]
                    : null,

                'assessments_count' =>
                    (int) $target->assessments_count,

                'findings_count' =>
                    (int) $target->findings_count,

                'last_assessment_at' =>
                    $lastAssessmentAt,

                'created_at' => $target->created_at,
                'updated_at' => $target->updated_at,
            ],
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
