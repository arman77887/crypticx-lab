<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PolarSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class PolarSubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    private const PROFESSIONAL_PRODUCT =
        '0cc0d541-5089-4b34-bd72-ff1572300847';

    private const TEAM_PRODUCT =
        '88ce66ad-4055-4824-b3c5-6dbecdf69887';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'billing.polar.products.professional',
            self::PROFESSIONAL_PRODUCT
        );

        config()->set(
            'billing.polar.products.team',
            self::TEAM_PRODUCT
        );
    }

    public function test_active_subscription_is_created_and_grants_entitlements(): void
    {
        $user = User::factory()->create();
        $subscriptionId = (string) Str::uuid();

        $subscription = $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T12:00:00Z'
            )
        );

        $this->assertSame(
            Subscription::STATUS_ACTIVE,
            $subscription->status
        );

        $this->assertSame(
            'professional',
            $subscription->plan_code
        );

        $this->assertSame(
            'polar',
            $subscription->provider
        );

        $this->assertSame(
            $user->id,
            $subscription->user_id
        );

        $this->assertTrue(
            $subscription->grantsEntitlements()
        );
    }

    public function test_lifecycle_active_past_due_active_then_revoked(): void
    {
        $user = User::factory()->create();
        $subscriptionId = (string) Str::uuid();

        $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T12:00:00Z'
            )
        );

        $subscription = $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.past_due',
                'past_due',
                '2026-09-20T13:00:00Z'
            )
        );

        $this->assertSame(
            Subscription::STATUS_PAST_DUE,
            $subscription->status
        );

        $this->assertFalse(
            $subscription->grantsEntitlements()
        );

        $subscription = $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T14:00:00Z'
            )
        );

        $this->assertSame(
            Subscription::STATUS_ACTIVE,
            $subscription->status
        );

        $this->assertTrue(
            $subscription->grantsEntitlements()
        );

        $subscription = $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.revoked',
                'revoked',
                '2026-09-20T15:00:00Z'
            )
        );

        $this->assertSame(
            Subscription::STATUS_EXPIRED,
            $subscription->status
        );

        $this->assertFalse(
            $subscription->grantsEntitlements()
        );
    }

    public function test_older_event_cannot_overwrite_newer_state(): void
    {
        $user = User::factory()->create();
        $subscriptionId = (string) Str::uuid();

        $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.past_due',
                'past_due',
                '2026-09-20T15:00:00Z'
            )
        );

        $subscription = $this->sync(
            $this->payload(
                $user,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T14:00:00Z'
            )
        );

        $subscription->refresh();

        $this->assertSame(
            Subscription::STATUS_PAST_DUE,
            $subscription->status
        );

        $this->assertFalse(
            $subscription->grantsEntitlements()
        );
    }

    public function test_wrong_product_is_rejected(): void
    {
        $user = User::factory()->create();

        $payload = $this->payload(
            $user,
            (string) Str::uuid(),
            'subscription.active',
            'active',
            '2026-09-20T12:00:00Z'
        );

        $payload['data']['product_id'] =
            (string) Str::uuid();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Polar subscription product does not match the configured plan.'
        );

        $this->sync($payload);
    }

    public function test_unknown_user_is_rejected(): void
    {
        $payload = $this->payload(
            User::factory()->make([
                'id' => (string) Str::uuid(),
            ]),
            (string) Str::uuid(),
            'subscription.active',
            'active',
            '2026-09-20T12:00:00Z'
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Polar subscription references an unknown user.'
        );

        $this->sync($payload);
    }

    public function test_existing_subscription_cannot_be_taken_over_by_another_user(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $subscriptionId = (string) Str::uuid();

        $this->sync(
            $this->payload(
                $owner,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T12:00:00Z'
            )
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Polar subscription ownership mismatch.'
        );

        $this->sync(
            $this->payload(
                $attacker,
                $subscriptionId,
                'subscription.active',
                'active',
                '2026-09-20T13:00:00Z'
            )
        );
    }

    public function test_unknown_status_fails_closed(): void
    {
        $user = User::factory()->create();

        $subscription = $this->sync(
            $this->payload(
                $user,
                (string) Str::uuid(),
                'subscription.updated',
                'something_unknown',
                '2026-09-20T12:00:00Z'
            )
        );

        $this->assertSame(
            Subscription::STATUS_EXPIRED,
            $subscription->status
        );

        $this->assertFalse(
            $subscription->grantsEntitlements()
        );
    }

    private function sync(array $payload): Subscription
    {
        return app(
            PolarSubscriptionSyncService::class
        )->sync($payload);
    }

    private function payload(
        User $user,
        string $subscriptionId,
        string $eventType,
        string $status,
        string $timestamp
    ): array {
        return [
            'type' => $eventType,
            'timestamp' => $timestamp,
            'api_version' => '2026-10',
            'data' => [
                'id' => $subscriptionId,
                'status' => $status,
                'customer_id' =>
                    (string) Str::uuid(),
                'product_id' =>
                    self::PROFESSIONAL_PRODUCT,
                'current_period_start' =>
                    '2026-09-20T00:00:00Z',
                'current_period_end' =>
                    '2026-10-20T00:00:00Z',
                'cancel_at_period_end' =>
                    false,
                'canceled_at' => null,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_code' =>
                        'professional',
                ],
            ],
        ];
    }
}
