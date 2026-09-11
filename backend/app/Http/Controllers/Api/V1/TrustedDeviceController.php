<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedDeviceController extends Controller
{
    public function __construct(
        private readonly DeviceTrustService $deviceTrust,
        private readonly PlatformSettingsService $settings,
        private readonly TelemetryService $telemetry,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $devices = TrustedDevice::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(
                fn (TrustedDevice $device) =>
                    $this->deviceResponse($device)
            );

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $devices,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $result = $this->deviceTrust->createPendingDevice(
            $request->user(),
            $request,
        );

        $this->telemetry->audit(
            $request,
            'device.registered',
            'security',
            $request->user(),
            [
                'device_id' => $result['device']->id,
                'status' => 'pending',
            ],
            'TrustedDevice',
            $result['device']->id,
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Device registered and awaiting verification.',
            'data' => [
                'device' =>
                    $this->deviceResponse($result['device']),

                /*
                 * Returned exactly at creation time.
                 * Only the SHA-256 hash is persisted server-side.
                 */
                'device_token' => $result['token'],
            ],
        ], 201);
    }

    public function verify(
        Request $request,
        TrustedDevice $device,
    ): JsonResponse {
        $user = $request->user();

        if ($device->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($device->revoked_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This device has been revoked.',
            ], 422);
        }

        if ($this->deviceTrust->isTrusted($device)) {
            return response()->json([
                'success' => false,
                'message' => 'This device is already trusted.',
            ], 422);
        }

        $validated = $request->validate([
            'device_token' => [
                'required',
                'string',
                'min:32',
                'max:'.DeviceTrustService::MAX_TOKEN_LENGTH,
            ],
            'name' => [
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        if (
            ! $this->deviceTrust->tokenMatches(
                $device,
                $validated['device_token'],
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Device verification token is invalid.',
            ], 422);
        }

        /*
         * Before the admin gate is enabled, this permits first-device
         * bootstrap using possession of the freshly issued device token.
         *
         * Once enforcement is enabled, a new device additionally requires
         * approval from an already trusted device belonging to the same user.
         */
        if (
            $this->settings->boolean(
                'trusted_device_admin_enforcement',
                false,
            )
        ) {
            $approverToken = (string) $request->header(
                'X-Device-Token',
                '',
            );

            $approver =
                $this->deviceTrust
                    ->findTrustedByTokenForUser(
                        $user,
                        $approverToken,
                    );

            if (! $approver) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Approval from an existing trusted device is required.',
                ], 403);
            }
        }

        $device = $this->deviceTrust->verify(
            $device,
            $request,
            $validated['name'] ?? null,
        );

        $sessionBound =
            $this->deviceTrust
                ->bindCurrentAccessToken(
                    $request,
                    $device,
                );

        $this->telemetry->audit(
            $request,
            'device.verified',
            'security',
            $user,
            [
                'device_id' => $device->id,
                'expires_at' => $device->expires_at,
            ],
            'TrustedDevice',
            $device->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Device trusted successfully.',
            'data' => [
                'device' => $this->deviceResponse($device),
            ],
        ]);
    }

    public function revoke(
        Request $request,
        TrustedDevice $device,
    ): JsonResponse {
        if ($device->user_id !== $request->user()->id) {
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
                    'The device currently authorizing administrator access cannot revoke itself while trusted-device enforcement is enabled. Use another trusted device or disable the administrator device gate first.',
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
            'device.revoked',
            'security',
            $request->user(),
            [
                'device_id' => $device->id,
                'terminated_session_count' =>
                    $terminatedSessions,
            ],
            'TrustedDevice',
            $device->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Device revoked successfully.',
        ]);
    }

    private function deviceResponse(
        TrustedDevice $device,
    ): array {
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
            'is_trusted' =>
                $this->deviceTrust->isTrusted($device),
            'revoked_at' => $device->revoked_at,
        ];
    }
}
