<?php

namespace Tests\Feature;

use App\Models\BillingWebhookEvent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolarWebhookIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_KEY =
        'crypticx-polar-integration-signing-key';

    private const SECRET =
        'whsec_'
        . 'Y3J5cHRpY3gtcG9sYXItaW50ZWdyYXRpb24tc2lnbmluZy1rZXk=';

    private const PRODUCT =
        '0cc0d541-5089-4b34-bd72-ff1572300847';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'billing.polar.webhook_secret',
            self::SECRET
        );

        config()->set(
            'billing.polar.products.professional',
            self::PRODUCT
        );
    }

    public function test_signed_webhook_creates_active_subscription(): void
    {
        $user = User::factory()->create();

        $body = $this->body(
            $user,
            (string) Str::uuid(),
            'subscription.active',
            'active'
        );

        $response = $this->postSigned($body);

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.processed',
                true
            )
            ->assertJsonPath(
                'data.duplicate',
                false
            );

        $subscription = Subscription::query()
            ->where('provider', 'polar')
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $subscription->user_id
        );

        $this->assertSame(
            Subscription::STATUS_ACTIVE,
            $subscription->status
        );

        $this->assertTrue(
            $subscription->grantsEntitlements()
        );

        $this->assertDatabaseCount(
            'billing_webhook_events',
            1
        );
    }

    public function test_same_delivery_is_idempotent(): void
    {
        $user = User::factory()->create();

        $body = $this->body(
            $user,
            (string) Str::uuid(),
            'subscription.active',
            'active'
        );

        $webhookId = 'msg_' . Str::random(20);
        $timestamp = (string) time();

        $first = $this->postSigned(
            $body,
            $webhookId,
            $timestamp
        );

        $second = $this->postSigned(
            $body,
            $webhookId,
            $timestamp
        );

        $first
            ->assertOk()
            ->assertJsonPath(
                'data.duplicate',
                false
            );

        $second
            ->assertOk()
            ->assertJsonPath(
                'data.duplicate',
                true
            );

        $this->assertDatabaseCount(
            'billing_webhook_events',
            1
        );

        $this->assertDatabaseCount(
            'subscriptions',
            1
        );
    }

    public function test_tampered_body_is_rejected_before_processing(): void
    {
        $user = User::factory()->create();

        $original = $this->body(
            $user,
            (string) Str::uuid(),
            'subscription.active',
            'active'
        );

        $webhookId = 'msg_' . Str::random(20);
        $timestamp = (string) time();

        $signature = $this->signature(
            $webhookId,
            $timestamp,
            $original
        );

        $tampered = str_replace(
            '"active"',
            '"past_due"',
            $original
        );

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/polar',
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
                    'v1,' . $signature,
            ],
            $tampered
        );

        $response
            ->assertStatus(401)
            ->assertJsonPath(
                'code',
                'POLAR_WEBHOOK_VERIFICATION_FAILED'
            );

        $this->assertDatabaseCount(
            'subscriptions',
            0
        );

        $this->assertDatabaseCount(
            'billing_webhook_events',
            0
        );
    }

    public function test_invalid_product_fails_without_granting_subscription(): void
    {
        $user = User::factory()->create();

        $payload = json_decode(
            $this->body(
                $user,
                (string) Str::uuid(),
                'subscription.active',
                'active'
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $payload['data']['product_id'] =
            (string) Str::uuid();

        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $response = $this->postSigned(
            $body
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'code',
                'POLAR_WEBHOOK_INVALID_PAYLOAD'
            );

        $this->assertDatabaseCount(
            'subscriptions',
            0
        );

        $event = BillingWebhookEvent::query()
            ->where('provider', 'polar')
            ->firstOrFail();

        $this->assertSame(
            BillingWebhookEvent::STATUS_FAILED,
            $event->processing_status
        );
    }

    private function postSigned(
        string $body,
        ?string $webhookId = null,
        ?string $timestamp = null
    ) {
        $webhookId ??=
            'msg_' . Str::random(20);

        $timestamp ??=
            (string) time();

        return $this->call(
            'POST',
            '/api/v1/webhooks/polar',
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
        return base64_encode(
            hash_hmac(
                'sha256',
                $webhookId
                    . '.'
                    . $timestamp
                    . '.'
                    . $body,
                self::SIGNING_KEY,
                true
            )
        );
    }

    private function body(
        User $user,
        string $subscriptionId,
        string $eventType,
        string $status
    ): string {
        return json_encode(
            [
                'type' => $eventType,
                'timestamp' =>
                    now()->toISOString(),
                'api_version' => '2026-10',
                'data' => [
                    'id' =>
                        $subscriptionId,
                    'status' =>
                        $status,
                    'customer_id' =>
                        (string) Str::uuid(),
                    'product_id' =>
                        self::PRODUCT,
                    'current_period_start' =>
                        now()->toISOString(),
                    'current_period_end' =>
                        now()
                            ->addMonth()
                            ->toISOString(),
                    'cancel_at_period_end' =>
                        false,
                    'canceled_at' => null,
                    'metadata' => [
                        'user_id' =>
                            $user->id,
                        'plan_code' =>
                            'professional',
                    ],
                ],
            ],
            JSON_THROW_ON_ERROR
        );
    }
}
