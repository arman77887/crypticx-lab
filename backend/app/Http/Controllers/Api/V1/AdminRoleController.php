<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $roles = Role::query()
            ->with([
                'permissions' => fn ($query) => $query
                    ->orderBy('group')
                    ->orderBy('name'),
            ])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'permissions' => $role->permissions
                    ->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'group' => $permission->group,
                        'description' => $permission->description,
                    ])
                    ->values(),
            ])
            ->values();

        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'group' => $permission->group,
                'description' => $permission->description,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'permissions' => $permissions,
                'summary' => [
                    'roles' => $roles->count(),
                    'system_roles' => $roles
                        ->where('is_system', true)
                        ->count(),
                    'permissions' => $permissions->count(),
                    'permission_groups' => $permissions
                        ->pluck('group')
                        ->unique()
                        ->count(),
                ],
                'capabilities' => [
                    'role_registry' => true,
                    'permission_registry' => true,
                    'role_mutation' => false,
                    'permission_mutation' => false,
                ],
                'generated_at' => now(),
            ],
        ]);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()
            ->roles()
            ->whereIn('slug', [
                'owner',
                'administrator',
            ])
            ->exists();
    }
}
