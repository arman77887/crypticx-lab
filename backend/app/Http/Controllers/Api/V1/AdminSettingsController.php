<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly DeviceTrustService $deviceTrust,
        private readonly TelemetryService $telemetry,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $this->settings->all(),

                'capabilities' => [
                    'public_registration_enforcement' => true,
                    'assessment_creation_enforcement' => true,

                    'mfa_enforcement' => false,
                    'trusted_device_admin_enforcement' =>
                        $this->settings->boolean(
                            'trusted_device_admin_enforcement',
                            false,
                        ),
                    'session_termination_on_device_revoke' => true,
                    'worker_resource_limits' => false,
                    'worker_egress_policy' => false,
                    'worker_isolation' => false,
                    'retention_cleanup' => false,
                ],

                'generated_at' => now(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'public_registration_enabled' => [
                'sometimes',
                'boolean',
            ],
            'assessment_creation_enabled' => [
                'sometimes',
                'boolean',
            ],
            'trusted_device_admin_enforcement' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if ($validated === []) {
            return response()->json([
                'success' => false,
                'message' => 'No supported settings supplied.',
            ], 422);
        }

        $before = $this->settings->all();

        /*
         * Enabling the admin gate requires proof that the caller already
         * controls a currently trusted device. This prevents accidental
         * administrator lockout and unauthenticated trust bootstrapping.
         */
        if (
            ($validated['trusted_device_admin_enforcement'] ?? false)
            && ! ($before['trusted_device_admin_enforcement'] ?? false)
        ) {
            $deviceToken = (string) $request->header(
                'X-Device-Token',
                '',
            );

            $trustedDevice =
                $this->deviceTrust->findTrustedByTokenForUser(
                    $request->user(),
                    $deviceToken,
                );

            if (! $trustedDevice) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Trust this browser/device before enabling the administrator device gate.',
                    'code' => 'TRUSTED_DEVICE_REQUIRED',
                ], 422);
            }
        }

        foreach ($validated as $key => $value) {
            $this->settings->update(
                $key,
                (bool) $value,
                $request->user(),
            );
        }

        $after = $this->settings->all();

        $changes = [];

        foreach ($validated as $key => $_) {
            $changes[$key] = [
                'from' => $before[$key],
                'to' => $after[$key],
            ];
        }

        $this->telemetry->audit(
            $request,
            'admin.settings.updated',
            'configuration',
            $request->user(),
            [
                'changes' => $changes,
            ],
            'PlatformSetting',
            null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Platform settings updated successfully.',
            'data' => [
                'settings' => $after,
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
