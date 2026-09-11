<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSecurityController extends Controller
{
    public function __construct(
        private readonly DeviceTrustService $deviceTrust,
        private readonly PlatformSettingsService $settings,
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

        $devices = TrustedDevice::query()
            ->with([
                'user:id,name,email',
                'revokedBy:id,name,email',
            ])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (TrustedDevice $device) => $this->deviceResponse($device));

        $summary = [
            'total' => $devices->count(),
            'trusted' => $devices->where('status', 'trusted')->count(),
            'pending' => $devices->where('status', 'pending')->count(),
            'expired' => $devices->where('status', 'expired')->count(),
            'revoked' => $devices->where('status', 'revoked')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $devices,
                'summary' => $summary,

                'capabilities' => [
                    'registry' => true,
                    'verification' => true,
                    'revocation' => true,

                    'admin_access_enforcement' =>
                        $this->settings->boolean(
                            'trusted_device_admin_enforcement',
                            false,
                        ),

                    /*
                     * Device revocation does not currently revoke active
                     * Sanctum personal access tokens.
                     */
                    'session_termination_on_revoke' => true,

                    'mfa_or_passkey_verification' => false,
                ],

                'generated_at' => now(),
            ],
        ]);
    }

    public function revoke(
        Request $request,
        TrustedDevice $device,
    ): JsonResponse {
        if (! $this->isPlatformAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($device->revoked_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Device is already revoked.',
            ], 422);
        }

        if (
            $this->settings->boolean(
                'trusted_device_admin_enforcement',
                false,
            )
            && $this->deviceTrust->requestUsesDevice(
                $request,
                $device,
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The trusted device currently authorizing this administrator session cannot revoke itself while enforcement is enabled.',
                'code' => 'CURRENT_TRUSTED_DEVICE_REQUIRED',
            ], 409);
        }

        $this->deviceTrust->revoke(
            $device,
            $request->user(),
        );

        $terminatedSessions =
            $this->deviceTrust
                ->terminateDeviceSessions(
                    $device,
                );

        $this->telemetry->audit(
            $request,
            'admin.device.revoked',
            'security',
            $request->user(),
            [
                'device_id' => $device->id,
                'device_user_id' => $device->user_id,
                'session_terminated' =>
                    $terminatedSessions > 0,
                'terminated_session_count' =>
                    $terminatedSessions,
            ],
            'TrustedDevice',
            $device->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Device trust revoked successfully.',
            'data' => [
                'device' => $this->deviceResponse(
                    $device->fresh([
                        'user:id,name,email',
                        'revokedBy:id,name,email',
                    ]),
                ),
            ],
        ]);
    }

    private function deviceResponse(TrustedDevice $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_type' => $device->device_type,
            'browser' => $device->browser,
            'platform' => $device->platform,
            'last_ip_address' => $device->last_ip_address,

            'first_seen_at' => $device->first_seen_at,
            'last_seen_at' => $device->last_seen_at,
            'verified_at' => $device->verified_at,
            'expires_at' => $device->expires_at,
            'revoked_at' => $device->revoked_at,

            'is_trusted' => $this->deviceTrust->isTrusted($device),
            'status' => $this->status($device),

            'user' => $device->user ? [
                'id' => $device->user->id,
                'name' => $device->user->name,
                'email' => $device->user->email,
            ] : null,

            'revoked_by' => $device->revokedBy ? [
                'id' => $device->revokedBy->id,
                'name' => $device->revokedBy->name,
                'email' => $device->revokedBy->email,
            ] : null,
        ];
    }

    private function status(TrustedDevice $device): string
    {
        if ($device->revoked_at !== null) {
            return 'revoked';
        }

        if (
            $device->expires_at !== null
            && $device->expires_at->isPast()
        ) {
            return 'expired';
        }

        if ($this->deviceTrust->isTrusted($device)) {
            return 'trusted';
        }

        return 'pending';
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
