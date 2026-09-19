<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserDeviceService
{
    public const TOKEN_BYTES = 64;
    public const MAX_TOKEN_LENGTH = 256;

    public function register(
        User $user,
        Request $request,
    ): array {
        $plainToken = Str::random(self::TOKEN_BYTES);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_token_hash' =>
                hash('sha256', $plainToken),
            'device_type' =>
                $this->deviceType($request->userAgent()),
            'browser' =>
                $this->browser($request->userAgent()),
            'platform' =>
                $this->platform($request->userAgent()),
            'first_ip_address' => $request->ip(),
            'last_ip_address' => $request->ip(),
            'registered_at' => now(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ]);

        return [
            'device' => $device,
            'token' => $plainToken,
        ];
    }

    public function findActiveForUser(
        User $user,
        string $plainToken,
    ): ?UserDevice {
        if (
            $plainToken === ''
            || strlen($plainToken) > self::MAX_TOKEN_LENGTH
        ) {
            return null;
        }

        return UserDevice::query()
            ->where('user_id', $user->id)
            ->where(
                'device_token_hash',
                hash('sha256', $plainToken),
            )
            ->whereNull('revoked_at')
            ->first();
    }

    public function touch(
        UserDevice $device,
        Request $request,
    ): void {
        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
        ])->save();
    }

    private function deviceType(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }

        return preg_match(
            '/Mobile|Android|iPhone|iPad/i',
            $ua,
        ) ? 'mobile' : 'desktop';
    }

    private function browser(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Edg/i', $ua) === 1 => 'Edge',
            preg_match('/Chrome/i', $ua) === 1 => 'Chrome',
            preg_match('/Firefox/i', $ua) === 1 => 'Firefox',
            preg_match('/Safari/i', $ua) === 1 => 'Safari',
            default => 'other',
        };
    }

    private function platform(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Android/i', $ua) === 1 => 'Android',
            preg_match('/iPhone|iPad/i', $ua) === 1 => 'iOS',
            preg_match('/Windows/i', $ua) === 1 => 'Windows',
            preg_match('/Macintosh|Mac OS/i', $ua) === 1 => 'macOS',
            preg_match('/Linux/i', $ua) === 1 => 'Linux',
            default => 'other',
        };
    }
}
