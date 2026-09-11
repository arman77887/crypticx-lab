<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\DeviceTrustService;
use App\Services\PlatformSettingsService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const TOKEN_LIFETIME_HOURS = 24;

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly DeviceTrustService $deviceTrust,
        private readonly TelemetryService $telemetry,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        if (
            ! $this->settings->boolean(
                'public_registration_enabled',
                true,
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Public account registration is currently disabled.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(
                $validated['email']
            ),
            'password' => $validated['password'],
        ]);

        $defaultRole = Role::query()
            ->where(
                'name',
                'Security Researcher',
            )
            ->first();

        if ($defaultRole) {
            $user->roles()->syncWithoutDetaching([
                $defaultRole->id,
            ]);
        }

        $user->load('roles');

        $token = $this->issueWebToken(
            $user,
            $request,
        );

        $this->telemetry->audit(
            $request,
            'auth.register',
            'authentication',
            $user,
            ['success' => true],
            'user',
            $user->id,
        );

        $this->telemetry->activity(
            $request,
            'user_registered',
            $user,
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Account created successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' =>
                    self::TOKEN_LIFETIME_HOURS
                    * 3600,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $user = User::query()
            ->where(
                'email',
                strtolower($validated['email']),
            )
            ->first();

        if (
            ! $user
            || ! Hash::check(
                $validated['password'],
                $user->password,
            )
        ) {
            $this->telemetry->audit(
                $request,
                'auth.login_failed',
                'authentication',
                null,
                ['success' => false],
            );

            $this->telemetry->activity(
                $request,
                'login_failed',
                null,
            );

            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are invalid.',
                ],
            ]);
        }

        $user->load('roles');

        $token = $this->issueWebToken(
            $user,
            $request,
        );

        $this->telemetry->audit(
            $request,
            'auth.login',
            'authentication',
            $user,
            ['success' => true],
            'user',
            $user->id,
        );

        $this->telemetry->activity(
            $request,
            'login',
            $user,
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Authenticated successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' =>
                    self::TOKEN_LIFETIME_HOURS
                    * 3600,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->telemetry->audit(
            $request,
            'auth.logout',
            'authentication',
            $user,
            ['success' => true],
            'user',
            $user->id,
        );

        $this->telemetry->activity(
            $request,
            'logout',
            $user,
        );

        $user->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' =>
                    $request->user()
                        ->load(
                            'roles.permissions'
                        ),
            ],
        ]);
    }

    private function issueWebToken(
        User $user,
        Request $request,
    ): string {
        $newToken = $user->createToken(
            'crypticx-web',
            ['*'],
            now()->addHours(
                self::TOKEN_LIFETIME_HOURS
            ),
        );

        $deviceToken = (string) $request->header(
            'X-Device-Token',
            '',
        );

        if ($deviceToken !== '') {
            $this->deviceTrust
                ->bindIssuedAccessToken(
                    $user,
                    $newToken->accessToken,
                    $deviceToken,
                );
        }

        return $newToken->plainTextToken;
    }
}
