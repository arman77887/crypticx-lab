<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\PolarWebhookService;
use App\Services\Billing\PolarWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PolarWebhookController extends Controller
{
    public function handle(
        Request $request,
        PolarWebhookVerifier $verifier,
        PolarWebhookService $webhooks,
    ): JsonResponse {
        try {
            /*
             * Verification happens against the exact raw body
             * before any payload is trusted or processed.
             */
            $payload = $verifier->verify($request);

            $result = $webhooks->process(
                $payload
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'code' =>
                    'POLAR_WEBHOOK_VERIFICATION_FAILED',
                'message' =>
                    'Webhook verification failed.',
            ], 401);
        } catch (InvalidArgumentException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'code' =>
                    'POLAR_WEBHOOK_INVALID_PAYLOAD',
                'message' =>
                    'Webhook payload is invalid.',
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'code' =>
                    'POLAR_WEBHOOK_PROCESSING_FAILED',
                'message' =>
                    'Webhook processing failed.',
            ], 500);
        }
    }
}
