<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
    }

    private function grantAssessmentViewPermission(
        User $user
    ): void {
        $permission = Permission::query()
            ->where('slug', 'assessments.view')
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Assessment History Test ' . Str::uuid(),
            'slug' => 'assessment-history-test-' . Str::uuid(),
            'description' => 'Assessment history regression test role.',
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
    }

    private function makeTarget(
        User $user,
        string $hostname
    ): Target {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = $hostname;
        $target->url = 'https://' . $hostname . '/';
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
        string $scanType
    ): Assessment {
        return Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'completed',
            'progress' => 100,
            'queued_at' => now()->subMinutes(3),
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'configuration' => [
                'scan_type' => $scanType,
            ],
            'execution_metadata' => [
                'test_fixture' => true,
            ],
        ]);
    }

    private function makeFinding(
        Assessment $assessment,
        Target $target,
        string $severity
    ): Finding {
        return Finding::query()->create([
            'assessment_id' => $assessment->id,
            'target_id' => $target->id,
            'type' => 'assessment_history_test',
            'fingerprint' => hash(
                'sha256',
                $assessment->id . $severity . Str::uuid()
            ),
            'title' => 'Assessment history fixture',
            'description' => 'Synthetic test finding.',
            'severity' => $severity,
            'confidence' => 'high',
            'evidence' => 'Synthetic fixture only.',
            'evidence_data' => [
                'test_fixture' => true,
            ],
            'remediation' => 'Synthetic fixture.',
            'status' => 'open',
        ]);
    }

    public function test_assessment_history_is_user_scoped_and_searchable(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $target = $this->makeTarget(
            $user,
            'example.com'
        );

        $otherTarget = $this->makeTarget(
            $other,
            'private.example'
        );

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'web_security'
        );

        $this->makeAssessment(
            $other,
            $otherTarget,
            'web_security'
        );

        $this->grantAssessmentViewPermission($user);
        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/assessments?search=example.com'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.id',
                $assessment->id
            )
            ->assertJsonPath(
                'data.data.0.target.hostname',
                'example.com'
            );
    }

    public function test_scan_type_filter_works_with_sqlite_json(): void
    {
        $user = User::factory()->create();

        $target = $this->makeTarget(
            $user,
            'scanner.example'
        );

        $web = $this->makeAssessment(
            $user,
            $target,
            'web_security'
        );

        $this->makeAssessment(
            $user,
            $target,
            'ssl_tls'
        );

        $this->grantAssessmentViewPermission($user);
        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/assessments?scan_type=web_security'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath(
                'data.data.0.id',
                $web->id
            )
            ->assertJsonPath(
                'data.data.0.configuration.scan_type',
                'web_security'
            );
    }

    public function test_assessment_contains_finding_severity_summary(): void
    {
        $user = User::factory()->create();

        $target = $this->makeTarget(
            $user,
            'severity.example'
        );

        $assessment = $this->makeAssessment(
            $user,
            $target,
            'api_security'
        );

        foreach ([
            'critical',
            'high',
            'medium',
            'low',
            'info',
            'informational',
        ] as $severity) {
            $this->makeFinding(
                $assessment,
                $target,
                $severity
            );
        }

        $this->grantAssessmentViewPermission($user);
        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/assessments?scan_type=api_security'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.data.0.findings_count',
                6
            )
            ->assertJsonPath(
                'data.data.0.critical_findings_count',
                1
            )
            ->assertJsonPath(
                'data.data.0.high_findings_count',
                1
            )
            ->assertJsonPath(
                'data.data.0.medium_findings_count',
                1
            )
            ->assertJsonPath(
                'data.data.0.low_findings_count',
                1
            )
            ->assertJsonPath(
                'data.data.0.informational_findings_count',
                2
            );
    }
}
