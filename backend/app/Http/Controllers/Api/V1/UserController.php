<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->latest()
            ->paginate(20);

        return UserResource::collection($users);
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
