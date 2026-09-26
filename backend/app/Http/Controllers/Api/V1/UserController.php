<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['roles', 'subscriptions'])
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('email', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%')
                            ->orWhere('id', $search);
                    });
                },
            )
            ->latest()
            ->paginate(20);

        $subscriptionMeta = $users
            ->getCollection()
            ->mapWithKeys(function (User $user) {
                return [
                    $user->id => [
                        'effective_plan' =>
                            $this->entitlements
                                ->effectivePlanCode($user),
                        'subscription' =>
                            $this->subscriptions
                                ->accountState($user),
                    ],
                ];
            });

        return UserResource::collection($users)
            ->additional([
                'subscription_meta' => $subscriptionMeta,
            ]);
    }

    public function show(User $user): UserResource
    {
        $user->load('roles');

        return new UserResource($user);
    }

    public function store(StoreUserRequest $request): UserResource|JsonResponse
    {
        $data = $request->validated();
        $roleSlug = $data['role'] ?? null;

        unset($data['role']);

        $role = $roleSlug
            ? Role::query()->where('slug', $roleSlug)->firstOrFail()
            : Role::query()->where('name', 'Security Researcher')->firstOrFail();

        if (
            $role->slug === 'owner' &&
            !$this->isOwner($request->user())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Only an Owner can create another Owner account.',
            ], 403);
        }

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create($data);
            $user->roles()->sync([$role->id]);

            return $user;
        });

        return new UserResource($user->fresh()->load('roles'));
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): UserResource|JsonResponse {
        $actor = $request->user();

        if ($this->isOwner($user) && !$this->isOwner($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Only an Owner can modify an Owner account.',
            ], 403);
        }

        $data = $request->validated();

        if (
            array_key_exists('password', $data) &&
            $data['password'] === null
        ) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh()->load('roles'));
    }

    public function updateRole(
        Request $request,
        User $user
    ): UserResource|JsonResponse {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,slug'],
        ]);

        $actor = $request->user();

        $role = Role::query()
            ->where('slug', $validated['role'])
            ->firstOrFail();

        $targetIsOwner = $this->isOwner($user);
        $actorIsOwner = $this->isOwner($actor);
        $newRoleIsOwner = $role->slug === 'owner';

        if (($targetIsOwner || $newRoleIsOwner) && !$actorIsOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Only an Owner can change Owner role assignments.',
            ], 403);
        }

        if (
            $targetIsOwner &&
            !$newRoleIsOwner &&
            $this->ownerCount() <= 1
        ) {
            return response()->json([
                'success' => false,
                'message' => 'The last Owner cannot be demoted.',
            ], 422);
        }

        $user->roles()->sync([$role->id]);

        return new UserResource($user->fresh()->load('roles'));
    }

    public function updateVerification(
        Request $request,
        User $user
    ): UserResource|JsonResponse {
        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $actor = $request->user();
        $targetIsOwner = $this->isOwner($user);
        $actorIsOwner = $this->isOwner($actor);

        if ($targetIsOwner && !$actorIsOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Only an Owner can change Owner verification.',
            ], 403);
        }

        if (
            $targetIsOwner &&
            $validated['verified'] === false
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Owner verification cannot be removed.',
            ], 422);
        }

        $wasVerified = $user->email_verified_at !== null;

        $user->forceFill([
            'email_verified_at' => $validated['verified']
                ? ($user->email_verified_at ?? now())
                : null,
        ])->save();

        $user->tokens()->delete();

        if (
            $validated['verified'] === true
            && ! $wasVerified
        ) {
            try {
                $user->notify(
                    new \App\Notifications\AccountVerifiedNotification()
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning(
                    'Account approval email notification failed.',
                    [
                        'user_id' => $user->id,
                        'exception' => $exception->getMessage(),
                    ]
                );
            }
        }

        return new UserResource(
            $user->fresh()->load('roles')
        );
    }

    public function destroy(
        Request $request,
        User $user
    ): JsonResponse {
        $actor = $request->user();

        if ($actor && $actor->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($this->isOwner($user)) {
            if (!$this->isOwner($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only an Owner can delete an Owner account.',
                ], 403);
            }

            if ($this->ownerCount() <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'The last Owner cannot be deleted.',
                ], 422);
            }
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->roles()->detach();
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    public function quotaOverrides(
        User $user,
    ): JsonResponse {
        $override = $user->quotaOverride()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'overrides' => [
                    'targets_total' => $override?->targets_total,
                    'assessments_monthly' => $override?->assessments_monthly,
                    'reports_monthly' => $override?->reports_monthly,
                    'monitoring_policies' => $override?->monitoring_policies,
                    'concurrent_assessments' => $override?->concurrent_assessments,
                ],
            ],
        ]);
    }

    public function updateQuotaOverrides(
        Request $request,
        User $user,
    ): JsonResponse {
        $validated = $request->validate([
            'targets_total' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'assessments_monthly' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'reports_monthly' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monitoring_policies' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'concurrent_assessments' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $keys = [
            'targets_total',
            'assessments_monthly',
            'reports_monthly',
            'monitoring_policies',
            'concurrent_assessments',
        ];

        $values = [];

        foreach ($keys as $key) {
            $values[$key] = array_key_exists($key, $validated)
                ? $validated[$key]
                : null;
        }

        $hasOverride = collect($values)
            ->contains(fn ($value) => $value !== null);

        if (! $hasOverride) {
            $user->quotaOverride()->delete();
        } else {
            $user->quotaOverride()->updateOrCreate(
                [],
                $values,
            );
        }

        return $this->quotaOverrides($user);
    }

    private function isOwner(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->roles()
            ->where('roles.slug', 'owner')
            ->exists();
    }

    private function ownerCount(): int
    {
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('roles.slug', 'owner');
            })
            ->count();
    }
}
