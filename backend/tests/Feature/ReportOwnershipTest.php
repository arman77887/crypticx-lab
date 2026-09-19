<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Database\Seeders\RbacSeeder;

class ReportOwnershipTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $reportIds = [];
    private array $roleIds = [];

    protected function tearDown(): void
    {
        if ($this->reportIds !== []) {
            Report::query()->whereIn('id', $this->reportIds)->delete();
        }

        if ($this->assessmentIds !== []) {
            Assessment::query()->whereIn('id', $this->assessmentIds)->delete();
        }

        if ($this->targetIds !== []) {
            Target::query()->whereIn('id', $this->targetIds)->delete();
        }

        if ($this->userIds !== []) {
            DB::table('user_roles')
                ->whereIn('user_id', $this->userIds)
                ->delete();
        }

        if ($this->roleIds !== []) {
            DB::table('role_permissions')
                ->whereIn('role_id', $this->roleIds)
                ->delete();

            Role::query()
                ->whereIn('id', $this->roleIds)
                ->delete();
        }

        if ($this->userIds !== []) {
            User::query()->whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = 'Report Ownership Test';
        $user->email = 'report-'.Str::uuid().'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        return $user;
    }

    private function grantReportPermissions(User $user): void
    {
        $this->seed(RbacSeeder::class);
        $permissions = Permission::query()
            ->whereIn('slug', [
                'reports.view',
                'reports.generate',
            ])
            ->get();

        $this->assertCount(
            2,
            $permissions,
            'Canonical report permissions are missing.',
        );

        $role = Role::query()->create([
            'name' => 'Report Ownership Test '.Str::uuid(),
            'slug' => 'report-ownership-test-'.Str::uuid(),
            'description' => 'Temporary report ownership regression-test role.',
            'is_system' => false,
        ]);

        $this->roleIds[] = $role->id;

        $role->permissions()->attach(
            $permissions->pluck('id')->all(),
        );

        $user->roles()->attach($role->id);
    }

    private function makeTarget(User $user): Target
    {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Report Ownership Target';
        $target->url = 'https://report-ownership.invalid/';
        $target->hostname = 'report-ownership.invalid';
        $target->scheme = 'https';
        $target->port = 443;
        $target->authorization_confirmed = true;
        $target->authorization_confirmed_at = now();
        $target->authorization_method = 'test_fixture';
        $target->status = 'active';
        $target->metadata = ['test_fixture' => true];
        $target->save();

        $this->targetIds[] = $target->id;

        return $target;
    }

    private function makeAssessment(
        User $user,
        Target $target,
        string $status = 'completed',
    ): Assessment {
        $assessment = new Assessment();
        $assessment->id = (string) Str::uuid();
        $assessment->user_id = $user->id;
        $assessment->target_id = $target->id;
        $assessment->profile = 'standard';
        $assessment->status = $status;
        $assessment->progress = $status === 'completed' ? 100 : 0;
        $assessment->queued_at = now()->subMinutes(3);
        $assessment->started_at =
            $status === 'completed' ? now()->subMinutes(2) : null;
        $assessment->completed_at =
            $status === 'completed' ? now()->subMinute() : null;
        $assessment->configuration = [];
        $assessment->execution_metadata = ['test_fixture' => true];
        $assessment->save();

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    private function makeReport(
        User $user,
        Target $target,
        Assessment $assessment,
    ): Report {
        $report = Report::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'assessment_id' => $assessment->id,
            'title' => 'Report Ownership Test',
            'status' => 'ready',
            'target_snapshot' => [
                'id' => $target->id,
                'name' => $target->name,
                'hostname' => $target->hostname,
                'url' => $target->url,
            ],
            'assessment_snapshot' => [
                'id' => $assessment->id,
                'profile' => $assessment->profile,
                'status' => $assessment->status,
                'finding_count' => 0,
            ],
            'findings_snapshot' => [],
            'risk_snapshot' => [
                'score' => 0,
                'level' => 'low',
            ],
            'intelligence_snapshot' => null,
            'metadata' => [
                'immutable_snapshot' => true,
                'test_fixture' => true,
            ],
            'generated_at' => now(),
        ]);

        $this->reportIds[] = $report->id;

        return $report;
    }

    public function test_owner_can_view_own_report(): void
    {
        $owner = $this->makeUser();
        $this->grantReportPermissions($owner);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);
        $report = $this->makeReport($owner, $target, $assessment);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.user_id', $owner->id);
    }

    public function test_user_cannot_view_another_users_report(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantReportPermissions($other);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);
        $report = $this->makeReport($owner, $target, $assessment);

        Sanctum::actingAs($other);

        $this->getJson("/api/v1/reports/{$report->id}")
            ->assertForbidden();
    }

    public function test_owner_can_download_own_report_pdf(): void
    {
        $owner = $this->makeUser();
        $this->grantReportPermissions($owner);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);
        $report = $this->makeReport($owner, $target, $assessment);

        Sanctum::actingAs($owner);

        $response = $this->get(
            "/api/v1/reports/{$report->id}/pdf"
        );

        $response->assertOk();

        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type'),
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $response->getContent(),
        );

        $this->assertStringContainsString(
            'attachment;',
            strtolower(
                (string) $response->headers->get(
                    'content-disposition'
                )
            ),
        );
    }


    public function test_user_cannot_download_another_users_report_pdf(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantReportPermissions($other);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);
        $report = $this->makeReport($owner, $target, $assessment);

        Sanctum::actingAs($other);

        $this->get("/api/v1/reports/{$report->id}/pdf")
            ->assertForbidden();
    }

    public function test_user_cannot_generate_report_for_another_users_assessment(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantReportPermissions($other);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);

        Sanctum::actingAs($other);

        $this->postJson(
            "/api/v1/assessments/{$assessment->id}/reports",
        )->assertForbidden();

        $this->assertDatabaseMissing('reports', [
            'user_id' => $other->id,
            'assessment_id' => $assessment->id,
        ]);
    }

    public function test_existing_assessment_report_is_reused_without_duplicate(): void
    {
        $owner = $this->makeUser();
        $this->grantReportPermissions($owner);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);
        $report = $this->makeReport($owner, $target, $assessment);

        Sanctum::actingAs($owner);

        $beforeCount = Report::query()
            ->where('user_id', $owner->id)
            ->where('assessment_id', $assessment->id)
            ->count();

        $response = $this->postJson(
            "/api/v1/assessments/{$assessment->id}/reports"
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Existing report loaded successfully.'
            )
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath(
                'data.assessment_id',
                $assessment->id
            );

        $afterCount = Report::query()
            ->where('user_id', $owner->id)
            ->where('assessment_id', $assessment->id)
            ->count();

        $this->assertSame(1, $beforeCount);
        $this->assertSame(1, $afterCount);
    }


    public function test_incomplete_assessment_cannot_generate_report(): void
    {
        $owner = $this->makeUser();
        $this->grantReportPermissions($owner);

        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment(
            $owner,
            $target,
            'queued',
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/v1/assessments/{$assessment->id}/reports",
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('reports', [
            'assessment_id' => $assessment->id,
        ]);
    }
}
