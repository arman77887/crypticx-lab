<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\UserDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    public function __construct(
        private readonly UserDeviceService $userDeviceService,
    ) {
    }

    private function authorizedUserDevice(
        Request $request,
    ): UserDevice|JsonResponse {
        $user = $request->user();

        $isAdmin = $user->roles()
            ->whereIn('slug', [
                'owner',
                'administrator',
            ])
            ->exists();

        if ($isAdmin) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This endpoint is only available for standard user device security.',
            ], 403);
        }

        $plainToken = (string) $request->header(
            'X-User-Device-Token',
            '',
        );

        $currentDevice = $this->userDeviceService
            ->findActiveForUser(
                $user,
                $plainToken,
            );

        if (! $currentDevice) {
            return response()->json([
                'success' => false,
                'message' =>
                    'An authorized user device is required.',
                'code' => 'USER_DEVICE_REQUIRED',
            ], 403);
        }

        $this->userDeviceService->touch(
            $currentDevice,
            $request,
        );

        return $currentDevice;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $currentDevice =
            $this->authorizedUserDevice($request);

        if ($currentDevice instanceof JsonResponse) {
            return $currentDevice;
        }

        $devices = UserDevice::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('registered_at')
            ->get()
            ->map(function (UserDevice $device) use (
                $currentDevice
            ) {
                return [
                    'id' => $device->id,
                    'device_type' => $device->device_type,
                    'browser' => $device->browser,
                    'platform' => $device->platform,
                    'registered_at' => $device->registered_at,
                    'last_seen_at' => $device->last_seen_at,
                    'current' =>
                        $currentDevice->id === $device->id,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'devices' => $devices,
            ],
        ]);
    }

    public function destroy(
        Request $request,
        string $deviceId,
    ): JsonResponse {
        $user = $request->user();

        $currentDevice =
            $this->authorizedUserDevice($request);

        if ($currentDevice instanceof JsonResponse) {
            return $currentDevice;
        }

        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->whereKey($deviceId)
            ->whereNull('revoked_at')
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Authorized device not found.',
            ], 404);
        }

        $isCurrent =
            $currentDevice->id === $device->id;

        $device->forceFill([
            'revoked_at' => now(),
        ])->save();

        if ($isCurrent) {
            $request->user()
                ->currentAccessToken()
                ?->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $isCurrent
                ? 'Current device removed. Please sign in again.'
                : 'Device authorization removed successfully.',
            'data' => [
                'current_device_removed' => $isCurrent,
            ],
        ]);
    }
}
