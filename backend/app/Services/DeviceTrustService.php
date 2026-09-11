<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceTrustService
{
    public const TOKEN_BYTES = 64;
    public const MAX_TOKEN_LENGTH = 256;
    public const TRUST_LIFETIME_DAYS = 30;

    public function createPendingDevice(
        User $user,
        Request $request,
    ): array {
        $plainToken = Str::random(self::TOKEN_BYTES);

        $device = TrustedDevice::create([
            'user_id' => $user->id,
            'device_token_hash' => hash('sha256', $plainToken),
            'name' => null,
            'device_type' => $this->deviceType($request->userAgent()),
            'browser' => $this->browser($request->userAgent()),
            'platform' => $this->platform($request->userAgent()),
            'last_ip_address' => $request->ip(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'is_trusted' => false,
            'verified_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_by' => null,
        ]);

        return [
            'device' => $device,
            'token' => $plainToken,
        ];
    }

    public function tokenMatches(
        TrustedDevice $device,
        string $plainToken,
    ): bool {
        if (
            $plainToken === ''
            || strlen($plainToken) > self::MAX_TOKEN_LENGTH
        ) {
            return false;
        }

        $candidate = hash('sha256', $plainToken);

        return hash_equals(
            (string) $device->device_token_hash,
            $candidate,
        );
    }

    public function findByToken(string $plainToken): ?TrustedDevice
    {
        if (
            $plainToken === ''
            || strlen($plainToken) > self::MAX_TOKEN_LENGTH
        ) {
            return null;
        }

        return TrustedDevice::query()
            ->where(
                'device_token_hash',
                hash('sha256', $plainToken),
            )
            ->first();
    }

    public function findTrustedByTokenForUser(
        User $user,
        string $plainToken,
    ): ?TrustedDevice {
        if (
            $plainToken === ''
            || strlen($plainToken) > self::MAX_TOKEN_LENGTH
        ) {
            return null;
        }

        $device = TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where(
                'device_token_hash',
                hash('sha256', $plainToken),
            )
            ->first();

        if (! $device || ! $this->isTrusted($device)) {
            return null;
        }

        return $device;
    }

    public function isTrusted(TrustedDevice $device): bool
    {
        return $device->is_trusted
            && $device->verified_at !== null
            && $device->revoked_at === null
            && (
                $device->expires_at === null
                || $device->expires_at->isFuture()
            );
    }

    public function verify(
        TrustedDevice $device,
        Request $request,
        ?string $name = null,
    ): TrustedDevice {
        $device->update([
            'name' => $name ?: $device->name,
            'is_trusted' => true,
            'verified_at' => now(),
            'expires_at' => now()->addDays(
                self::TRUST_LIFETIME_DAYS
            ),
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
            'revoked_at' => null,
            'revoked_by' => null,
        ]);

        return $device->fresh();
    }

    public function touch(
        TrustedDevice $device,
        Request $request,
    ): void {
        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
        ])->save();
    }

    public function requestUsesDevice(
        Request $request,
        TrustedDevice $device,
    ): bool {
        $plainToken = (string) $request->header(
            'X-Device-Token',
            '',
        );

        return $this->tokenMatches(
            $device,
            $plainToken,
        );
    }

    public function bindCurrentAccessToken(
        Request $request,
        TrustedDevice $device,
    ): bool {
        $user = $request->user();

        if (
            ! $user
            || $device->user_id !== $user->id
            || ! $this->isTrusted($device)
        ) {
            return false;
        }

        $accessToken = $user->currentAccessToken();

        if (! $accessToken) {
            return false;
        }

        $accessToken->forceFill([
            'trusted_device_id' => $device->id,
        ])->save();

        return true;
    }

    public function bindIssuedAccessToken(
        User $user,
        object $accessToken,
        string $plainDeviceToken,
    ): ?TrustedDevice {
        $device = $this->findTrustedByTokenForUser(
            $user,
            $plainDeviceToken,
        );

        if (! $device) {
            return null;
        }

        $accessToken->forceFill([
            'trusted_device_id' => $device->id,
        ])->save();

        return $device;
    }

    public function terminateDeviceSessions(
        TrustedDevice $device,
    ): int {
        $user = $device->user()->first();

        if (! $user) {
            return 0;
        }

        return $user->tokens()
            ->where(
                'trusted_device_id',
                $device->id,
            )
            ->delete();
    }

    public function revoke(
        TrustedDevice $device,
        ?User $revokedBy = null,
    ): void {
        $device->update([
            'is_trusted' => false,
            'revoked_at' => now(),
            'revoked_by' => $revokedBy?->id,
        ]);
    }

    private function deviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        return preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)
            ? 'mobile'
            : 'desktop';
    }

    private function browser(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Edg/i', $userAgent) === 1 => 'Edge',
            preg_match('/Chrome/i', $userAgent) === 1 => 'Chrome',
            preg_match('/Firefox/i', $userAgent) === 1 => 'Firefox',
            preg_match('/Safari/i', $userAgent) === 1 => 'Safari',
            default => 'Other',
        };
    }

    private function platform(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Windows/i', $userAgent) === 1 => 'Windows',
            preg_match('/Android/i', $userAgent) === 1 => 'Android',
            preg_match('/iPhone|iPad|iOS/i', $userAgent) === 1 => 'iOS',
            preg_match('/Mac OS X/i', $userAgent) === 1 => 'macOS',
            preg_match('/Linux/i', $userAgent) === 1 => 'Linux',
            default => 'Other',
        };
    }
}
