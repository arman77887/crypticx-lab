<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountEntitlementUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_plan_limits_and_own_usage(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $userTargetOne = $this->makeTarget(
            $user,
            'entitlement-user-one.invalid',
        );

        $userTargetTwo = $this->makeTarget(
            $user,
            'entitlement-user-two.invalid',
        );

        $otherTarget = $this->makeTarget(
            $otherUser,
            'entitlement-other.invalid',
        );

        for ($i = 0; $i < 4; $i++) {
            $this->makeAssessment($user, $userTargetOne);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->makeAssessment($otherUser, $otherTarget);
        }

        $response = $this->getJson(
            '/api/v1/account/entitlements'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.limits.targets_total', 3)
            ->assertJsonPath(
                'data.limits.assessments_monthly',
                25,
            )
            ->assertJsonPath('data.limits.reports_monthly', 5)
            ->assertJsonPath(
                'data.limits.monitoring_policies',
                0,
            )
            ->assertJsonPath(
                'data.limits.concurrent_assessments',
                1,
            )
            ->assertJsonPath('data.usage.targets_total', 2)
            ->assertJsonPath(
                'data.usage.assessments_monthly',
                4,
            );

        $this->assertNotNull($userTargetTwo);
    }

    public function test_entitlement_usage_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/account/entitlements')
            ->assertUnauthorized();
    }

    private function makeTarget(
        User $user,
        string $hostname,
    ): Target {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Entitlement Usage Target';
        $target->url = "https://{$hostname}/";
        $target->hostname = $hostname;
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method = 'test_fixture';
        $target->status = 'active';
        $target->metadata = ['test_fixture' => true];
        $target->save();

        return $target;
    }

    private function makeAssessment(
        User $user,
        Target $target,
    ): Assessment {
        $assessment = new Assessment();
        $assessment->id = (string) Str::uuid();
        $assessment->user_id = $user->id;
        $assessment->target_id = $target->id;
        $assessment->profile = 'standard';
        $assessment->status = 'completed';
        $assessment->progress = 100;
        $assessment->queued_at = now()->subMinutes(3);
        $assessment->started_at = now()->subMinutes(2);
        $assessment->completed_at = now()->subMinute();
        $assessment->configuration = [];
        $assessment->execution_metadata = [
            'test_fixture' => true,
        ];
        $assessment->save();

        return $assessment;
    }
}
