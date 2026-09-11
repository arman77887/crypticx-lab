<?php

namespace App\Http\Middleware;

use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTrustedAdminDevice
{
    public function __construct(
        private readonly DeviceTrustService $deviceTrust,
        private readonly PlatformSettingsService $settings,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        /*
         * This middleware is intentionally inert outside the
         * administrator API namespace.
         */
        if (! $request->is('api/v1/admin/*')) {
            return $next($request);
        }

        if (
            ! $this->settings->boolean(
                'trusted_device_admin_enforcement',
                false,
            )
        ) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $this->denied(
                'Authentication is required.',
                401,
            );
        }

        $plainDeviceToken = (string) $request->header(
            'X-Device-Token',
            '',
        );

        $device = $this->deviceTrust
            ->findTrustedByTokenForUser(
                $user,
                $plainDeviceToken,
            );

        if (! $device) {
            return $this->denied(
                'A valid trusted device is required for administrator access.',
                403,
            );
        }

        /*
         * Device possession by itself is insufficient. The authenticated
         * Sanctum token must also have been issued/bound to this exact
         * trusted device.
         *
         * This deliberately rejects legacy or unbound bearer tokens.
         */
        $accessToken = $user->currentAccessToken();

        $boundDeviceId = $accessToken
            ? (string) ($accessToken->trusted_device_id ?? '')
            : '';

        if (
            $boundDeviceId === ''
            || ! hash_equals(
                (string) $device->id,
                $boundDeviceId,
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The authenticated session is not bound to this trusted device. Sign in again from this trusted device.',
                'code' =>
                    'TRUSTED_DEVICE_SESSION_MISMATCH',
            ], 403);
        }

        $request->attributes->set(
            'trusted_device_id',
            $device->id,
        );

        $this->deviceTrust->touch(
            $device,
            $request,
        );

        return $next($request);
    }

    private function denied(
        string $message,
        int $status,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'TRUSTED_DEVICE_REQUIRED',
        ], $status);
    }
}
