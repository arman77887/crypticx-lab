<?php

namespace App\Services;

use App\Mail\UserDeviceAuthorizationCodeMail;
use App\Models\User;
use App\Models\UserDeviceAuthorizationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserDeviceAuthorizationService
{
    private const CODE_TTL_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;
    private const REQUEST_TOKEN_LENGTH = 64;

    public function __construct(
        private readonly UserDeviceService $userDeviceService,
    ) {
    }

    public function sendCode(
        User $user,
        Request $request,
    ): string {
        UserDeviceAuthorizationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $code = (string) random_int(100000, 999999);
        $requestToken = Str::random(
            self::REQUEST_TOKEN_LENGTH
        );

        UserDeviceAuthorizationCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'request_token_hash' =>
                hash('sha256', $requestToken),
            'device_type' =>
                $this->deviceType($request->userAgent()),
            'browser' =>
                $this->browser($request->userAgent()),
            'platform' =>
                $this->platform($request->userAgent()),
            'ip_address' => $request->ip(),
            'attempts' => 0,
            'expires_at' =>
                now()->addSeconds(self::CODE_TTL_SECONDS),
            'used_at' => null,
        ]);

        Mail::to($user->email)->send(
            new UserDeviceAuthorizationCodeMail(
                $user,
                $code,
            )
        );

        return $requestToken;
    }

    public function verifyAndRegister(
        string $requestToken,
        string $code,
        Request $request,
    ): array {
        $authorization =
            UserDeviceAuthorizationCode::query()
                ->where(
                    'request_token_hash',
                    hash('sha256', $requestToken),
                )
                ->whereNull('used_at')
                ->first();

        if (
            ! $authorization
            || $authorization->expires_at->isPast()
            || $authorization->attempts >= self::MAX_ATTEMPTS
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'The device verification code is invalid or expired.',
                ],
            ]);
        }

        if (
            ! Hash::check(
                $code,
                $authorization->code_hash,
            )
        ) {
            $authorization->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [
                    'The device verification code is invalid or expired.',
                ],
            ]);
        }

        $user = User::query()
            ->with('roles')
            ->find($authorization->user_id);

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => [
                    'The device verification request is invalid.',
                ],
            ]);
        }

        // Device OTP must never authorize an administrator device.
        $isAdministrator = $user->roles->contains(
            fn ($role) => in_array(
                $role->slug,
                ['owner', 'administrator'],
                true
            )
        );

        if ($isAdministrator) {
            throw ValidationException::withMessages([
                'code' => [
                    'Administrator device verification is not available here.',
                ],
            ]);
        }

        $authorization->forceFill([
            'used_at' => now(),
        ])->save();

        return [
            'user' => $user,
            ...$this->userDeviceService->register(
                $user,
                $request,
            ),
        ];
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
