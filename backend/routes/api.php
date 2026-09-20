<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'service' => 'CrypticX Lab API',
            'version' => 'v1',
            'status' => 'operational',
        ]);
    });

    Route::get('/plans', [
        \App\Http\Controllers\Api\V1\PlanCatalogController::class,
        'index',
    ])->middleware('throttle:60,1');

    Route::get('/billing/status', [
        \App\Http\Controllers\Api\V1\BillingController::class,
        'status',
    ])->middleware('throttle:60,1');

    Route::post('/webhooks/paddle', [
        \App\Http\Controllers\Api\V1\PaddleWebhookController::class,
        'handle',
    ])->middleware('throttle:120,1');

    Route::post('/webhooks/polar', [
        \App\Http\Controllers\Api\V1\PolarWebhookController::class,
        'handle',
    ])->middleware('throttle:120,1');

    Route::post(
        '/auth/register',
        [AuthController::class, 'register']
    )->middleware('throttle:auth-register');

    Route::post(
        '/auth/login',
        [AuthController::class, 'login']
    )->middleware('throttle:auth-login');

    Route::post(
        '/auth/password/forgot',
        [PasswordRecoveryController::class, 'forgot']
    )->middleware('throttle:auth-password-forgot');

    Route::post(
        '/auth/password/verify',
        [PasswordRecoveryController::class, 'verify']
    )->middleware('throttle:10,1');

    Route::post(
        '/auth/password/reset',
        [PasswordRecoveryController::class, 'reset']
    )->middleware('throttle:auth-password-reset');


    Route::post(
        '/auth/device/verify',
        [AuthController::class, 'verifyUserDevice']
    )->middleware('throttle:10,1');

    Route::post(
        '/auth/passkey/authenticate/verify',
        [AuthController::class, 'verifyPasskeyLogin']
    )->middleware('throttle:10,1');

    Route::post('/contact', [
        \App\Http\Controllers\Api\V1\ContactController::class,
        'store',
    ])->middleware('throttle:contact-submit');

    Route::middleware([
        'auth:sanctum',
        'trusted.admin.device',
    ])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/account/devices', [
            \App\Http\Controllers\Api\V1\UserDeviceController::class,
            'index',
        ])->middleware('throttle:60,1');

        Route::delete('/account/devices/{deviceId}', [
            \App\Http\Controllers\Api\V1\UserDeviceController::class,
            'destroy',
        ])->middleware('throttle:20,1');

        Route::get('/auth/passkey/status', [
            \App\Http\Controllers\Api\V1\PasskeyEnrollmentController::class,
            'status',
        ])->middleware('throttle:30,1');

        Route::post('/auth/passkey/register/options', [
            \App\Http\Controllers\Api\V1\PasskeyEnrollmentController::class,
            'options',
        ])->middleware('throttle:10,1');

        Route::post('/auth/passkey/register/verify', [
            \App\Http\Controllers\Api\V1\PasskeyEnrollmentController::class,
            'verify',
        ])->middleware('throttle:10,1');

        Route::get('/account/entitlements', [
            \App\Http\Controllers\Api\V1\AccountEntitlementController::class,
            'show',
        ]);

        Route::get('/account/subscription', [
            \App\Http\Controllers\Api\V1\AccountSubscriptionController::class,
            'show',
        ]);


        Route::post('/account/billing/checkout', [
            \App\Http\Controllers\Api\V1\BillingController::class,
            'checkout',
        ])->middleware('throttle:10,1');

        Route::post('/tools/dns-lookup', [
            \App\Http\Controllers\Api\V1\DnsToolController::class,
            'lookup',
        ])->middleware('throttle:30,1');


        Route::post('/tools/web-security', [
            \App\Http\Controllers\Api\V1\WebSecurityToolController::class,
            'analyze',
        ])->middleware('throttle:20,1');


        Route::post('/tools/phishing-link-analyzer', [
            \App\Http\Controllers\Api\V1\PhishingLinkAnalyzerController::class,
            'analyze',
        ])->middleware('throttle:30,1');


        Route::post('/tools/api-security', [
            \App\Http\Controllers\Api\V1\ApiSecurityToolController::class,
            'analyze',
        ])->middleware('throttle:20,1');


        Route::post('/tools/dns-intelligence', [
            \App\Http\Controllers\Api\V1\DnsIntelligenceToolController::class,
            'analyze',
        ])->middleware('throttle:20,1');

        Route::post('/tools/ssl-tls', [
            \App\Http\Controllers\Api\V1\SslTlsToolController::class,
            'analyze',
        ])->middleware('throttle:15,1');


        Route::post('/tools/sql-lab', [
            \App\Http\Controllers\Api\V1\SqlLabToolController::class,
            'analyze',
        ])->middleware('throttle:60,1');

        Route::post('/tools/data-lab', [
            \App\Http\Controllers\Api\V1\DataLabToolController::class,
            'analyze',
        ])->middleware('throttle:60,1');


        Route::post('/tools/recon', [
            \App\Http\Controllers\Api\V1\ReconToolController::class,
            'analyze',
        ])->middleware('throttle:12,1');

        Route::post('/tools/network-analysis', [
            \App\Http\Controllers\Api\V1\NetworkAnalysisToolController::class,
            'analyze',
        ])->middleware('throttle:6,1');

        Route::get('/security/devices', [
            \App\Http\Controllers\Api\V1\TrustedDeviceController::class,
            'index',
        ]);

        Route::post('/security/devices', [
            \App\Http\Controllers\Api\V1\TrustedDeviceController::class,
            'register',
        ])->middleware('throttle:10,1');

        Route::post('/security/devices/{device}/verify', [
            \App\Http\Controllers\Api\V1\TrustedDeviceController::class,
            'verify',
        ])->middleware('throttle:10,1');

        Route::post('/security/devices/{device}/revoke', [
            \App\Http\Controllers\Api\V1\TrustedDeviceController::class,
            'revoke',
        ])->middleware('throttle:10,1');

        Route::middleware('permission:targets.view')->group(function () {
            Route::get('/targets', [
                \App\Http\Controllers\Api\V1\TargetController::class,
                'index',
            ]);

            Route::get('/admin/targets', [
                \App\Http\Controllers\Api\V1\AdminTargetController::class,
                'index',
            ]);
        });

        Route::middleware('permission:targets.manage')->group(function () {
            Route::post('/targets', [
                \App\Http\Controllers\Api\V1\TargetController::class,
                'store',
            ]);

            Route::patch('/admin/targets/{target}', [
                \App\Http\Controllers\Api\V1\AdminTargetController::class,
                'update',
            ]);
        });

        Route::middleware('permission:assessments.view')->group(function () {
            Route::get('/assessments', [
                \App\Http\Controllers\Api\V1\AssessmentController::class,
                'index',
            ]);

            Route::get('/admin/assessments', [
                \App\Http\Controllers\Api\V1\AdminAssessmentController::class,
                'index',
            ]);

            Route::get('/admin/workers', [
                \App\Http\Controllers\Api\V1\AdminWorkerController::class,
                'index',
            ]);

            Route::get('/admin/monitoring', [
                \App\Http\Controllers\Api\V1\AdminMonitoringController::class,
                'index',
            ]);

            Route::get('/admin/monitoring/events', [
                \App\Http\Controllers\Api\V1\AdminMonitoringController::class,
                'events',
            ]);

            Route::get('/admin/monitoring/deliveries', [
                \App\Http\Controllers\Api\V1\AdminMonitoringController::class,
                'deliveries',
            ]);

            Route::get('/assessments/{assessment}/intelligence', [
                \App\Http\Controllers\Api\V1\AssessmentController::class,
                'intelligence',
            ]);

            Route::get('/assessments/{assessment}', [
                \App\Http\Controllers\Api\V1\AssessmentController::class,
                'show',
            ]);
        });

        Route::middleware('permission:assessments.create')->group(function () {
            Route::post('/assessments', [
                \App\Http\Controllers\Api\V1\AssessmentController::class,
                'store',
            ]);
        });

        Route::middleware('permission:assessments.view')->group(function () {
            Route::get('/monitoring', [
                \App\Http\Controllers\Api\V1\MonitoringPolicyController::class,
                'index',
            ]);

            Route::get('/monitoring/events', [
                \App\Http\Controllers\Api\V1\MonitoringHistoryController::class,
                'events',
            ]);

            Route::get('/monitoring/events/{event}', [
                \App\Http\Controllers\Api\V1\MonitoringHistoryController::class,
                'event',
            ]);

            Route::get('/monitoring/deliveries', [
                \App\Http\Controllers\Api\V1\MonitoringHistoryController::class,
                'deliveries',
            ]);

            Route::get('/targets/{target}/monitoring', [
                \App\Http\Controllers\Api\V1\MonitoringPolicyController::class,
                'show',
            ]);
        });

        Route::middleware('permission:assessments.create')->group(function () {
            Route::put('/targets/{target}/monitoring', [
                \App\Http\Controllers\Api\V1\MonitoringPolicyController::class,
                'upsert',
            ]);

            Route::delete('/targets/{target}/monitoring', [
                \App\Http\Controllers\Api\V1\MonitoringPolicyController::class,
                'disable',
            ]);
        });

        Route::middleware('permission:assessments.view')->group(function () {
            Route::get('/monitoring/notifications/preferences', [
                \App\Http\Controllers\Api\V1\MonitoringNotificationPreferenceController::class,
                'show',
            ]);
        });

        Route::middleware('permission:assessments.create')->group(function () {
            Route::put('/monitoring/notifications/preferences', [
                \App\Http\Controllers\Api\V1\MonitoringNotificationPreferenceController::class,
                'upsert',
            ]);
        });

        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports', [
                \App\Http\Controllers\Api\V1\ReportController::class,
                'index',
            ]);

            Route::get('/reports/{report}', [
                \App\Http\Controllers\Api\V1\ReportController::class,
                'show',
            ]);

            Route::get('/reports/{report}/pdf', [
                \App\Http\Controllers\Api\V1\ReportController::class,
                'pdf',
            ]);

            Route::get('/admin/reports', [
                \App\Http\Controllers\Api\V1\AdminReportController::class,
                'index',
            ]);

            Route::get('/admin/reports/{report}', [
                \App\Http\Controllers\Api\V1\AdminReportController::class,
                'show',
            ]);

            Route::get('/admin/reports/{report}/pdf', [
                \App\Http\Controllers\Api\V1\AdminReportController::class,
                'pdf',
            ]);
        });

        Route::middleware('permission:reports.generate')->group(function () {
            Route::post('/assessments/{assessment}/reports', [
                \App\Http\Controllers\Api\V1\ReportController::class,
                'store',
            ]);
        });

        Route::middleware('permission:findings.view')->group(function () {
            Route::get('/dashboard', [
                \App\Http\Controllers\Api\V1\DashboardController::class,
                'index',
            ]);
        });

        Route::middleware('permission:findings.view')->group(function () {
            Route::get('/finding-lifecycles', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'lifecycleIndex',
            ]);

            Route::get('/admin/finding-lifecycles', [
                \App\Http\Controllers\Api\V1\AdminFindingController::class,
                'index',
            ]);

            Route::get('/findings', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'index',
            ]);

            Route::get('/findings/{finding}', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'show',
            ]);

            Route::get('/findings/{finding}/lifecycle', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'lifecycle',
            ]);

            Route::get('/findings/{finding}/history', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'history',
            ]);

            Route::get('/findings/{finding}/assessments', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'assessments',
            ]);

            Route::get('/findings/{finding}/evidence', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'evidence',
            ]);

            Route::get('/findings/{finding}/remediation', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'remediation',
            ]);
        });

        Route::patch('/findings/{finding}/status', [
            \App\Http\Controllers\Api\V1\FindingController::class,
            'updateStatus',
        ]);

        Route::middleware('permission:findings.manage')->group(function () {
            Route::patch('/admin/finding-lifecycles/{lifecycle}/status', [
                \App\Http\Controllers\Api\V1\AdminFindingController::class,
                'updateStatus',
            ]);
        });

        Route::middleware('permission:findings.confirm')->group(function () {
            Route::patch('/findings/{finding}/confirm', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'confirm',
            ]);
        });

        Route::middleware('permission:findings.resolve')->group(function () {
            Route::patch('/findings/{finding}/resolve', [
                \App\Http\Controllers\Api\V1\FindingController::class,
                'resolve',
            ]);
        });

        Route::middleware('permission:users.view')->group(function () {
            Route::get('/admin/dashboard', [
                \App\Http\Controllers\Api\V1\AdminDashboardController::class,
                'index',
            ]);

            Route::get('/admin/roles', [
            \App\Http\Controllers\Api\V1\AdminRoleController::class,
            'index',
        ]);

        Route::get('/admin/settings', [
            \App\Http\Controllers\Api\V1\AdminSettingsController::class,
            'index',
        ]);

        Route::patch('/admin/settings', [
            \App\Http\Controllers\Api\V1\AdminSettingsController::class,
            'update',
        ]);

        Route::get('/admin/security', [
            \App\Http\Controllers\Api\V1\AdminSecurityController::class,
            'index',
        ]);

        Route::post('/admin/security/devices/{device}/revoke', [
            \App\Http\Controllers\Api\V1\AdminSecurityController::class,
            'revoke',
        ]);

        Route::middleware('permission:audit.view')->group(function () {
                Route::get('/admin/telemetry', [
                    \App\Http\Controllers\Api\V1\AdminTelemetryController::class,
                    'index',
                ]);
            });
        });

        Route::middleware('permission:users.view')->group(function () {
            Route::get('/users', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'index',
            ]);

            Route::get('/users/{user}', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'show',
            ]);

            Route::get('/rbac-test/users-view', [
                \App\Http\Controllers\Api\V1\RbacTestController::class,
                'usersView',
            ]);
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::post('/users', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'store',
            ]);

            Route::patch('/users/{user}', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'update',
            ]);

            Route::patch('/users/{user}/role', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'updateRole',
            ]);

            Route::patch('/users/{user}/verification', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'updateVerification',
            ]);


            Route::delete('/users/{user}', [
                \App\Http\Controllers\Api\V1\UserController::class,
                'destroy',
            ]);
        });
    });
});
