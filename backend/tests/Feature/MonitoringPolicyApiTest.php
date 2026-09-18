<?php

namespace Tests\Feature;

use App\Models\MonitoringPolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringPolicyApiTest extends TestCase
{
    private array $createdUserIds = [];
    private array $createdTargetIds = [];
    private array $createdRoleIds = [];
    private array $createdSubscriptionIds = [];

    protected function tearDown(): void
    {
        /*
         * Explicit cleanup because this project currently points at a
         * hosted PostgreSQL development database. Do not use
         * RefreshDatabase against it.
         */
        if ($this->createdTargetIds !== []) {
            MonitoringPolicy::query()
                ->whereIn('target_id', $this->createdTargetIds)
                ->delete();

            Target::query()
                ->whereIn('id', $this->createdTargetIds)
                ->delete();
        }

        if ($this->createdUserIds !== []) {
            DB::table('user_roles')
                ->whereIn('user_id', $this->createdUserIds)
                ->delete();
        }

        if ($this->createdRoleIds !== []) {
            DB::table('role_permissions')
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

    private function makeUser(): User
    {
        $user = new User();

        $user->id = (string) Str::uuid();
        $user->name = 'Monitoring API Test';
        $user->email =
            'monitoring-'.Str::uuid().'@example.invalid';

        /*
         * Authentication is supplied by Sanctum::actingAs().
         * A non-usable random password is stored only to satisfy
         * databases where password is required.
         */
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->createdUserIds[] = $user->id;

        $subscription = new Subscription();
        $subscription->id = (string) Str::uuid();
        $subscription->user_id = $user->id;
        $subscription->plan_code = 'professional';
        $subscription->status = Subscription::STATUS_ACTIVE;
        $subscription->current_period_start = now()->subMinute();
        $subscription->current_period_end = now()->addDay();
        $subscription->save();

        $this->createdSubscriptionIds[] = $subscription->id;

        return $user;
    }

    private function grantMonitoringPermissions(User $user): void
    {
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
            'name' => 'Monitoring API Test '.Str::uuid(),
            'slug' => 'monitoring-api-test-'.Str::uuid(),
            'description' => 'Temporary monitoring API regression-test role.',
            'is_system' => false,
        ]);

        $this->createdRoleIds[] = $role->id;

        $role->permissions()->attach(
            $permissions->pluck('id')->all()
        );

        $user->roles()->attach($role->id);
    }

    private function makeTarget(
        User $user,
        bool $authorized = true,
        string $status = 'active',
    ): Target {
        $target = new Target();

        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Monitoring Test Target';
        $target->url = 'https://monitoring-test.invalid/';
        $target->hostname = 'monitoring-test.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = $authorized;
        $target->authorization_confirmed_at =
            $authorized ? now() : null;
        $target->authorization_method =
            $authorized ? 'test_fixture' : null;
        $target->status = $status;
        $target->metadata = [
            'test_fixture' => true,
        ];

        $target->save();

        $this->createdTargetIds[] = $target->id;

        return $target;
    }

    public function test_owner_can_create_read_update_and_disable_monitoring_policy(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $this->grantMonitoringPermissions($user);
        Sanctum::actingAs($user);

        $create = $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'enabled' => true,
                'profile' => 'standard',
                'interval_minutes' => 60,
                'configuration' => [],
            ],
        );

        $create
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_id', $target->id)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.profile', 'standard')
            ->assertJsonPath('data.interval_minutes', 60);

        $this->assertDatabaseHas('monitoring_policies', [
            'user_id' => $user->id,
            'target_id' => $target->id,
            'enabled' => true,
            'profile' => 'standard',
            'interval_minutes' => 60,
        ]);

        $policy = MonitoringPolicy::query()
            ->where('target_id', $target->id)
            ->firstOrFail();

        $this->assertNotNull($policy->next_run_at);
        $this->assertTrue($policy->next_run_at->isFuture());

        $show = $this->getJson(
            "/api/v1/targets/{$target->id}/monitoring"
        );

        $show
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $policy->id);

        $update = $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'profile' => 'deep',
                'interval_minutes' => 120,
            ],
        );

        $update
            ->assertOk()
            ->assertJsonPath('data.profile', 'deep')
            ->assertJsonPath('data.interval_minutes', 120);

        $index = $this->getJson('/api/v1/monitoring');

        $index
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            collect($index->json('data'))
                ->contains(
                    fn (array $item) =>
                        ($item['target_id'] ?? null) === $target->id
                )
        );

        $disable = $this->deleteJson(
            "/api/v1/targets/{$target->id}/monitoring"
        );

        $disable
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', false);

        /*
         * DELETE is intentionally a disable operation. Historical
         * policy state remains persisted.
         */
        $this->assertDatabaseHas('monitoring_policies', [
            'target_id' => $target->id,
            'enabled' => false,
        ]);

        $policy->refresh();

        $this->assertNull($policy->next_run_at);
    }

    public function test_monitoring_validation_rejects_unsafe_schedule_values(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget($user);

        $this->grantMonitoringPermissions($user);
        Sanctum::actingAs($user);

        $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'interval_minutes' => 14,
            ],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'interval_minutes',
            ]);

        $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'profile' => 'unbounded',
            ],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile',
            ]);

        $this->assertDatabaseMissing('monitoring_policies', [
            'target_id' => $target->id,
        ]);
    }

    public function test_user_cannot_manage_another_users_target_monitoring(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $target = $this->makeTarget($owner);

        $this->grantMonitoringPermissions($other);
        Sanctum::actingAs($other);

        $this->getJson(
            "/api/v1/targets/{$target->id}/monitoring"
        )->assertForbidden();

        $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'enabled' => true,
                'interval_minutes' => 60,
            ],
        )->assertForbidden();

        $this->deleteJson(
            "/api/v1/targets/{$target->id}/monitoring"
        )->assertForbidden();

        $this->assertDatabaseMissing('monitoring_policies', [
            'target_id' => $target->id,
        ]);
    }

    public function test_monitoring_cannot_be_enabled_for_unauthorized_target(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget(
            $user,
            authorized: false,
        );

        $this->grantMonitoringPermissions($user);
        Sanctum::actingAs($user);

        $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'enabled' => true,
                'interval_minutes' => 60,
            ],
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('monitoring_policies', [
            'target_id' => $target->id,
        ]);
    }

    public function test_monitoring_cannot_be_enabled_for_inactive_target(): void
    {
        $user = $this->makeUser();
        $target = $this->makeTarget(
            $user,
            authorized: true,
            status: 'inactive',
        );

        $this->grantMonitoringPermissions($user);
        Sanctum::actingAs($user);

        $this->putJson(
            "/api/v1/targets/{$target->id}/monitoring",
            [
                'enabled' => true,
                'interval_minutes' => 60,
            ],
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('monitoring_policies', [
            'target_id' => $target->id,
        ]);
    }
}
