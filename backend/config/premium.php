<?php

return [

    /*
     * Canonical commercial plan identifiers.
     *
     * Security boundaries such as authorization checks, SSRF protection,
     * forbidden network ranges, scanner runtime isolation and hard resource
     * ceilings are NOT entitlements and must never be bypassed by a paid plan.
     */

    'default_plan' => 'free',

    'plans' => [

        'free' => [
            'name' => 'Open Source',
            'price_monthly_usd' => 0,

            'capabilities' => [
                'core_tools' => true,
                'security_labs' => true,
                'target_management' => true,
                'assessments' => true,
                'reports' => true,
                'monitoring' => false,
                'priority_processing' => false,
                'team_workspace' => false,
                'shared_targets' => false,
                'team_rbac' => false,
                'team_audit_activity' => false,
            ],

            'limits' => [
                'targets_total' => 3,
                'assessments_monthly' => 25,
                'reports_monthly' => 5,
                'monitoring_policies' => 0,
                'concurrent_assessments' => 1,
            ],
        ],

        'professional' => [
            'name' => 'Professional',
            'price_monthly_usd' => 19,

            'capabilities' => [
                'core_tools' => true,
                'security_labs' => true,
                'target_management' => true,
                'assessments' => true,
                'reports' => true,
                'monitoring' => true,
                'priority_processing' => true,
                'team_workspace' => false,
                'shared_targets' => false,
                'team_rbac' => false,
                'team_audit_activity' => false,
            ],

            'limits' => [
                'targets_total' => 50,
                'assessments_monthly' => 1000,
                'reports_monthly' => 250,
                'monitoring_policies' => 10,
                'concurrent_assessments' => 2,
            ],
        ],

        'team' => [
            'name' => 'Team',
            'price_monthly_usd' => 49,

            'capabilities' => [
                'core_tools' => true,
                'security_labs' => true,
                'target_management' => true,
                'assessments' => true,
                'reports' => true,
                'monitoring' => true,
                'priority_processing' => true,
                'team_workspace' => true,
                'shared_targets' => true,
                'team_rbac' => true,
                'team_audit_activity' => true,
            ],

            'limits' => [
                'targets_total' => 250,
                'assessments_monthly' => 5000,
                'reports_monthly' => 2000,
                'monitoring_policies' => 100,
                'concurrent_assessments' => 5,
            ],
        ],

    ],

];
