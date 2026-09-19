<?php

namespace Tests\Feature;

use App\Exceptions\PlanQuotaException;
use App\Models\Assessment;
use App\Models\Target;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentMonthlyQuotaRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_user_can_reach_25_then_26th_is_blocked(): void
    {
        $user = User::factory()->create();
        $target = $this->makeTarget($user);

        $quota = app(QuotaService::class);

        // 24/25: another assessment is still allowed.
        for ($i = 0; $i < 24; $i++) {
            $this->makeAssessment($user, $target);
        }

        $quota->assertAssessmentCreationAllowed($user);

        $this->assertSame(
            24,
            Assessment::query()
                ->where('user_id', $user->id)
                ->count(),
        );

        // Simulate the successfully-created 25th assessment.
        $this->makeAssessment($user, $target);

        $this->assertSame(
            25,
            Assessment::query()
                ->where('user_id', $user->id)
                ->count(),
        );

        // At 25/25, the next creation attempt must be rejected.
        try {
            $quota->assertAssessmentCreationAllowed($user);

            $this->fail(
                'Expected monthly assessment quota to reject the 26th assessment.'
            );
        } catch (PlanQuotaException $exception) {
            $this->assertSame(
                'ASSESSMENT_MONTHLY_LIMIT_REACHED',
                $exception->errorCode(),
            );

            $this->assertSame(
                422,
                $exception->statusCode(),
            );

            $this->assertSame(
                'Your monthly assessment limit has been reached.',
                $exception->getMessage(),
            );
        }
    }

    private function makeTarget(User $user): Target
    {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Quota Runtime Target';
        $target->url = 'https://quota-runtime.invalid/';
        $target->hostname = 'quota-runtime.invalid';
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
