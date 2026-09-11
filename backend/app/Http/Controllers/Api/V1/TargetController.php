<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\PlanQuotaException;
use App\Http\Requests\StoreTargetRequest;
use App\Models\Target;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TargetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $targets = Target::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $targets,
        ]);
    }

    public function store(
        StoreTargetRequest $request,
        QuotaService $quotas,
    ): JsonResponse {
        try {
            $quotas->assertTargetCreationAllowed(
                $request->user(),
            );
        } catch (PlanQuotaException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }

        $url = $request->validated('url');

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return response()->json([
                'success' => false,
                'message' => 'A valid target hostname is required.',
            ], 422);
        }

        $target = Target::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'url' => $url,
            'hostname' => strtolower($parts['host']),
            'scheme' => strtolower($parts['scheme'] ?? 'https'),
            'port' => $parts['port'] ?? null,
            'authorization_confirmed' => true,
            'authorization_confirmed_at' => now(),
            'authorization_method' => $request->validated('authorization_method'),
            'status' => 'active',
            'metadata' => [
                'created_via' => 'api',
                'target_reference' => (string) Str::uuid(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authorized target registered successfully.',
            'data' => $target,
        ], 201);
    }
}
