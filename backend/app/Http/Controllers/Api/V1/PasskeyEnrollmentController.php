<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebAuthn\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasskeyEnrollmentController extends Controller
{
    public function __construct(
        private readonly WebAuthnService $webAuthn
    ) {
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $this->isAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access required.',
            ], 403);
        }

        $data = $this->webAuthn
            ->createRegistrationOptions($user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $this->isAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access required.',
            ], 403);
        }

        $credentials = $user->webAuthnCredentials()
            ->orderByDesc('created_at')
            ->get();

        $activeCount = $credentials
            ->filter(fn ($credential) => $credential->isActive())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $activeCount > 0,
                'active_count' => $activeCount,
                'total_count' => $credentials->count(),
                'credentials' => $credentials->map(function ($credential) {
                    return [
                        'id' => $credential->id,
                        'name' => $credential->name,
                        'active' => $credential->isActive(),
                        'transports' => $credential->transports,
                        'created_at' => optional($credential->created_at)?->toIso8601String(),
                        'last_used_at' => optional($credential->last_used_at)?->toIso8601String(),
                        'revoked_at' => optional($credential->revoked_at)?->toIso8601String(),
                    ];
                })->values(),
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $this->isAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access required.',
            ], 403);
        }

        $validated = $request->validate([
            'transaction_id' => [
                'required',
                'uuid',
            ],
            'credential' => [
                'required',
                'array',
            ],
        ]);

        try {
            $credential = $this->webAuthn
                ->verifyRegistration(
                    $user,
                    $validated['transaction_id'],
                    $validated['credential']
                );
        } catch (Throwable $exception) {
            Log::warning(
                'WebAuthn passkey enrollment failed.',
                [
                    'user_id' => $user->id,
                    'exception' => $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Passkey verification failed.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Passkey registered successfully.',
            'data' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'transports' => $credential->transports,
                'created_at' => $credential->created_at,
            ],
        ], 201);
    }

    private function isAdmin($user): bool
    {
        return $user->roles()
            ->whereIn(
                'slug',
                [
                    'owner',
                    'administrator',
                ]
            )
            ->exists();
    }
}
