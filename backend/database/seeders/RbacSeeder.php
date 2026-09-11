<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'Users'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'Users'],

            // Targets
            ['name' => 'View Targets', 'slug' => 'targets.view', 'group' => 'Targets'],
            ['name' => 'Manage Targets', 'slug' => 'targets.manage', 'group' => 'Targets'],
            ['name' => 'Verify Targets', 'slug' => 'targets.verify', 'group' => 'Targets'],

            // Assessments
            ['name' => 'View Assessments', 'slug' => 'assessments.view', 'group' => 'Assessments'],
            ['name' => 'Create Assessments', 'slug' => 'assessments.create', 'group' => 'Assessments'],
            ['name' => 'Execute Assessments', 'slug' => 'assessments.execute', 'group' => 'Assessments'],
            ['name' => 'Stop Assessments', 'slug' => 'assessments.stop', 'group' => 'Assessments'],

            // Findings
            ['name' => 'View Findings', 'slug' => 'findings.view', 'group' => 'Findings'],
            ['name' => 'Manage Findings', 'slug' => 'findings.manage', 'group' => 'Findings'],
            ['name' => 'Confirm Findings', 'slug' => 'findings.confirm', 'group' => 'Findings'],
            ['name' => 'Resolve Findings', 'slug' => 'findings.resolve', 'group' => 'Findings'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'Reports'],
            ['name' => 'Generate Reports', 'slug' => 'reports.generate', 'group' => 'Reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'group' => 'Reports'],

            // Workers
            ['name' => 'View Workers', 'slug' => 'workers.view', 'group' => 'Workers'],
            ['name' => 'Manage Workers', 'slug' => 'workers.manage', 'group' => 'Workers'],

            // Audit
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'group' => 'Audit'],

            // Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'Settings'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'Settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $roles = [
            'Owner' => [
                'slug' => 'owner',
                'description' => 'Full platform ownership and administrative control.',
            ],
            'Administrator' => [
                'slug' => 'administrator',
                'description' => 'Platform administration and operational management.',
            ],
            'Security Researcher' => [
                'slug' => 'security-researcher',
                'description' => 'Authorized security assessment and research operations.',
            ],
            'Analyst' => [
                'slug' => 'analyst',
                'description' => 'Security findings, analysis and reporting operations.',
            ],
            'Auditor' => [
                'slug' => 'auditor',
                'description' => 'Read-only security and audit visibility.',
            ],
        ];

        foreach ($roles as $name => $data) {
            Role::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $name,
                    'description' => $data['description'],
                    'is_system' => true,
                ]
            );
        }

        $allPermissions = Permission::pluck('id', 'slug');
        $allRoles = Role::pluck('id', 'slug');

        $rolePermissions = [
            'owner' => array_column($permissions, 'slug'),

            'administrator' => [
                'users.view', 'users.manage',
                'targets.view', 'targets.manage', 'targets.verify',
                'assessments.view', 'assessments.create', 'assessments.execute', 'assessments.stop',
                'findings.view', 'findings.manage', 'findings.confirm', 'findings.resolve',
                'reports.view', 'reports.generate', 'reports.export',
                'workers.view', 'workers.manage',
                'audit.view',
                'settings.view', 'settings.manage',
            ],

            'security-researcher' => [
                'targets.view', 'targets.manage',
                'assessments.view', 'assessments.create', 'assessments.execute', 'assessments.stop',
                'findings.view',
                'reports.view', 'reports.generate', 'reports.export',
            ],

            'analyst' => [
                'targets.view',
                'assessments.view',
                'findings.view', 'findings.manage', 'findings.confirm', 'findings.resolve',
                'reports.view', 'reports.generate', 'reports.export',
            ],

            'auditor' => [
                'users.view',
                'targets.view',
                'assessments.view',
                'findings.view',
                'reports.view',
                'workers.view',
                'audit.view',
                'settings.view',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleId = $allRoles[$roleSlug];

            $permissionIds = collect($permissionSlugs)
                ->map(fn (string $permissionSlug) => $allPermissions[$permissionSlug])
                ->values()
                ->all();

            Role::findOrFail($roleId)
                ->permissions()
                ->sync($permissionIds);
        }
    }
}
