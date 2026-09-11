<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'projects.view',
                'projects.manage',
            ])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();

            DB::table('permissions')
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }

    public function down(): void
    {
        /*
         * Project is no longer a CrypticX Lab domain entity.
         *
         * This migration is intentionally irreversible because restoring
         * obsolete RBAC permissions would reintroduce a permission surface
         * with no corresponding backend resource or authorization domain.
         */
    }
};
