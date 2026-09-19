<?php

namespace Tests\Feature;
use Database\Seeders\RbacSeeder;

use App\Models\MonitoringNotificationPreference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringNotificationPreferenceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed("Database\\Seeders\\RbacSeeder");
    }

    private array $createdUserIds = [];
    private array $createdRoleIds = [];
    private array $createdSubscriptionIds = [];

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            MonitoringNotificationPreference::query()
                ->whereIn('user_id', $this->createdUserIds)
                ->delete();

            \DB::table('user_roles')
                ->whereIn('user_id', $this->createdUserIds)
                ->delete();
        }

        if ($this->createdRoleIds !== []) {
            \DB::table('role_permissions')
                ->whereIn('role_id', $this->createdRoleIds)
                ->delete();

            Role::query()
                ->whereIn('id', $this->createdRoleIds)
                ->delete();
        }

        if ($this->createdSubscriptionIds !== []) {
            Subscription::query()
                ->whereIn('id', $this->createdSubscriptionIds)
                ->delete();
        }

        if ($this->createdUserIds !== []) {
            User::query()
                ->whereIn('id', $this->createdUserIds)
                ->delete();
        }

        parent::tearDown();
    }

    private function user(): User
    {
        $user = User::factory()->create();

        $this->createdUserIds[] = $user->id;

        $subscription = new Subscription();
        $subscription->id = (string) Str::uuid();
        $subscription->user_id = $user->id;
        $subscription->plan_code = 'professional';
        $subscription->status = Subscription::STATUS_ACTIVE;
        $subscription->current_period_start =
            now()->subMinute();
        $subscription->current_period_end =
            now()->addDay();
        $subscription->save();

        $this->createdSubscriptionIds[] =
            $subscription->id;

        $permissions = Permission::query()
            ->whereIn('slug', [
                'assessments.view',
                'assessments.create',
            ])
            ->get();

        $this->assertCount(
            2,
            $permissions,
            'Canonical monitoring permissions are missing.'
        );

        $role = Role::query()->create([
            'name' =>
                'Monitoring Preference Test '.Str::uuid(),
            'slug' =>
                'monitoring-preference-test-'.Str::uuid(),
            'description' =>
                'Temporary monitoring preference API test role.',
            'is_system' => false,
        ]);

        $this->createdRoleIds[] = $role->id;

        $role->permissions()->attach(
            $permissions->pluck('id')->all()
        );

        $user->roles()->attach($role->id);

        Sanctum::actingAs(
            $user,
            ['*']
        );

        return $user;
    }

    public function test_missing_preference_returns_digest_defaults(): void
    {
        $this->user();

        $response = $this->getJson(
            '/api/v1/monitoring/notifications/preferences'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.persisted', false)
            ->assertJsonPath(
                'data.daily_digest_enabled',
                true
            )
            ->assertJsonPath(
                'data.daily_digest_timezone',
                'UTC'
            )
            ->assertJsonPath(
                'data.daily_digest_hour',
                8
            );

        $this->assertDatabaseCount(
            'monitoring_notification_preferences',
            0
        );
    }

    public function test_digest_preferences_can_be_saved(): void
    {
        $user = $this->user();

        $response = $this->putJson(
            '/api/v1/monitoring/notifications/preferences',
            [
                'daily_digest_enabled' => true,
                'daily_digest_timezone' => 'Asia/Riyadh',
                'daily_digest_hour' => 9,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.daily_digest_enabled',
                true
            )
            ->assertJsonPath(
                'data.daily_digest_timezone',
                'Asia/Riyadh'
            )
            ->assertJsonPath(
                'data.daily_digest_hour',
                9
            );

        $this->assertDatabaseHas(
            'monitoring_notification_preferences',
            [
                'user_id' => $user->id,
                'daily_digest_enabled' => 1,
                'daily_digest_timezone' => 'Asia/Riyadh',
                'daily_digest_hour' => 9,
            ]
        );
    }

    public function test_partial_update_preserves_digest_values(): void
    {
        $user = $this->user();

        MonitoringNotificationPreference::query()->create([
            'user_id' => $user->id,
            'email_enabled' => true,
            'event_types' =>
                MonitoringNotificationPreference::defaultEventTypes(),
            'minimum_risk_delta' => 1,
            'daily_digest_enabled' => true,
            'daily_digest_timezone' => 'Asia/Riyadh',
            'daily_digest_hour' => 10,
        ]);

        $response = $this->putJson(
            '/api/v1/monitoring/notifications/preferences',
            [
                'minimum_risk_delta' => 5,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.minimum_risk_delta',
                5
            )
            ->assertJsonPath(
                'data.daily_digest_enabled',
                true
            )
            ->assertJsonPath(
                'data.daily_digest_timezone',
                'Asia/Riyadh'
            )
            ->assertJsonPath(
                'data.daily_digest_hour',
                10
            );
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->user();

        $this->putJson(
            '/api/v1/monitoring/notifications/preferences',
            [
                'daily_digest_timezone' =>
                    'Not/A_Real_Timezone',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'daily_digest_timezone'
            );
    }

    public function test_invalid_digest_hour_is_rejected(): void
    {
        $this->user();

        $this->putJson(
            '/api/v1/monitoring/notifications/preferences',
            [
                'daily_digest_hour' => 24,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'daily_digest_hour'
            );
    }
}
