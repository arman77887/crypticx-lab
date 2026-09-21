<?php

namespace Tests\Unit;

use App\Services\Billing\PolarWebhookVerifier;
use Illuminate\Http\Request;
use Tests\TestCase;

class PolarWebhookVerifierTest extends TestCase
{
    private const SIGNING_KEY =
        'crypticx-polar-test-signing-key';

    private const SECRET =
        'whsec_'
        . 'Y3J5cHRpY3gtcG9sYXItdGVzdC1zaWduaW5nLWtleQ==';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'billing.polar.webhook_secret',
            self::SECRET
        );
    }

    public function test_valid_signature_is_accepted(): void
    {
        $body = $this->payload();
        $timestamp = (string) time();

        $request = $this->signedRequest(
            $body,
            $timestamp
        );

        $payload = app(
            PolarWebhookVerifier::class
        )->verify($request);

        $this->assertSame(
            'subscription.active',
            $payload['type']
        );

        $this->assertSame(
            'sub_test_123',
            $payload['data']['id']
        );
    }

    public function test_modified_body_is_rejected(): void
    {
        $originalBody = $this->payload();
        $timestamp = (string) time();

        $signature = $this->signature(
            'msg_test_123',
            $timestamp,
            $originalBody
        );

        $modifiedBody = str_replace(
            '"active"',
            '"past_due"',
            $originalBody
        );

        $request = Request::create(
            '/api/v1/webhooks/polar',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',
                'HTTP_WEBHOOK_ID' =>
                    'msg_test_123',
                'HTTP_WEBHOOK_TIMESTAMP' =>
                    $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' =>
                    'v1,' . $signature,
            ],
            $modifiedBody
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Invalid Polar webhook signature.'
        );

        app(
            PolarWebhookVerifier::class
        )->verify($request);
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $body = $this->payload();
        $timestamp = (string) time();

        $request = Request::create(
            '/api/v1/webhooks/polar',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',
                'HTTP_WEBHOOK_ID' =>
                    'msg_test_123',
                'HTTP_WEBHOOK_TIMESTAMP' =>
                    $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' =>
                    'v1,'
                    . base64_encode(
                        random_bytes(32)
                    ),
            ],
            $body
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Invalid Polar webhook signature.'
        );

        app(
            PolarWebhookVerifier::class
        )->verify($request);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $body = $this->payload();

        $timestamp = (string) (
            time() - 601
        );

        $request = $this->signedRequest(
            $body,
            $timestamp
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Polar webhook timestamp is outside tolerance.'
        );

        app(
            PolarWebhookVerifier::class
        )->verify($request);
    }

    public function test_missing_signature_headers_are_rejected(): void
    {
        $request = Request::create(
            '/api/v1/webhooks/polar',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',
            ],
            $this->payload()
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Missing Polar webhook signature headers.'
        );

        app(
            PolarWebhookVerifier::class
        )->verify($request);
    }

    public function test_invalid_json_is_rejected_after_valid_signature(): void
    {
        $body = '{"invalid":';
        $timestamp = (string) time();

        $request = $this->signedRequest(
            $body,
            $timestamp
        );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Invalid Polar webhook JSON.'
        );

        app(
            PolarWebhookVerifier::class
        )->verify($request);
    }

    private function signedRequest(
        string $body,
        string $timestamp
    ): Request {
        $webhookId = 'msg_test_123';

        return Request::create(
            '/api/v1/webhooks/polar',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' =>
                    'application/json',
                'HTTP_WEBHOOK_ID' =>
                    $webhookId,
                'HTTP_WEBHOOK_TIMESTAMP' =>
                    $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' =>
                    'v1,'
                    . $this->signature(
                        $webhookId,
                        $timestamp,
                        $body
                    ),
            ],
            $body
        );
    }

    private function signature(
        string $webhookId,
        string $timestamp,
        string $body
    ): string {
        $signedContent =
            $webhookId
            . '.'
            . $timestamp
            . '.'
            . $body;

        return base64_encode(
            hash_hmac(
                'sha256',
                $signedContent,
                self::SIGNING_KEY,
                true
            )
        );
    }

    private function payload(): string
    {
        return json_encode(
            [
                'type' =>
                    'subscription.active',
                'timestamp' =>
                    '2026-01-01T00:00:00.000000Z',
                'data' => [
                    'id' => 'sub_test_123',
                    'status' => 'active',
                ],
            ],
            JSON_THROW_ON_ERROR
        );
    }
}
