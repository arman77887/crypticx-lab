<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordRecoveryCodeMail;
use App\Models\PasswordRecoveryCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryController extends Controller
{
    private const CODE_TTL_MINUTES = 10;
    private const RESET_TOKEN_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $genericResponse = [
            'success' => true,
            'message' =>
                'If an account exists for that email, a verification code has been sent.',
        ];

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json($genericResponse);
        }

        PasswordRecoveryCode::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        PasswordRecoveryCode::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'attempts' => 0,
        ]);

        Mail::to($email)->send(
            new PasswordRecoveryCodeMail($code)
        );

        return response()->json($genericResponse);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));

        $recovery = PasswordRecoveryCode::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (
            ! $recovery ||
            $recovery->verified_at !== null ||
            $recovery->expires_at->isPast() ||
            $recovery->attempts >= self::MAX_ATTEMPTS
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is invalid or expired.',
                ],
            ]);
        }

        if (! Hash::check($validated['code'], $recovery->code_hash)) {
            $recovery->increment('attempts');

            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is invalid or expired.',
                ],
            ]);
        }

        $plainResetToken = Str::random(64);

        $recovery->forceFill([
            'verified_at' => now(),
            'reset_token_hash' => hash('sha256', $plainResetToken),
            'reset_token_expires_at' =>
                now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'reset_token' => $plainResetToken,
            ],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $email = strtolower(trim($validated['email']));
        $tokenHash = hash('sha256', $validated['reset_token']);

        $recovery = PasswordRecoveryCode::query()
            ->where('email', $email)
            ->where('reset_token_hash', $tokenHash)
            ->whereNotNull('verified_at')
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        if (
            ! $recovery ||
            ! $recovery->reset_token_expires_at ||
            $recovery->reset_token_expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'reset_token' => [
                    'The password reset session is invalid or expired.',
                ],
            ]);
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'reset_token' => [
                    'The password reset session is invalid or expired.',
                ],
            ]);
        }

        DB::transaction(function () use (
            $user,
            $recovery,
            $validated,
        ): void {
            $user->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ])->save();

            // Revoke existing Sanctum sessions after password recovery.
            $user->tokens()->delete();

            $recovery->forceFill([
                'used_at' => now(),
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
            ])->save();

            PasswordRecoveryCode::query()
                ->where('email', $user->email)
                ->whereKeyNot($recovery->id)
                ->delete();
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Your password has been reset successfully. Please sign in again.',
        ]);
    }
}
