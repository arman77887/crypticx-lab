<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaddleWebhookService;
use App\Services\Billing\PaddleWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaddleWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaddleWebhookVerifier $verifier,
        PaddleWebhookService $webhooks,
    ): JsonResponse {
        try {
            $verifier->verify($request);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' =>
                    'Webhook signature verification failed.',
            ], 400);
        }

        $payload = json_decode(
            $request->getContent(),
            true
        );

        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_WEBHOOK_PAYLOAD',
                'message' =>
                    'Webhook payload must be valid JSON.',
            ], 400);
        }

        try {
            $result = $webhooks->process(
                $payload
            );
        } catch (Throwable $exception) {
            Log::error(
                'Paddle webhook processing failed.',
                [
                    'event_id' =>
                        $payload['event_id'] ?? null,
                    'event_type' =>
                        $payload['event_type'] ?? null,
                    'exception' =>
                        get_class($exception),
                ]
            );

            /*
             * Return non-2xx so Paddle retries transient failures.
             * Sensitive payloads and secrets are not logged.
             */
            return response()->json([
                'success' => false,
                'code' => 'WEBHOOK_PROCESSING_FAILED',
                'message' =>
                    'Webhook processing failed.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
