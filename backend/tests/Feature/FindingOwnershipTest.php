<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingLifecycle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FindingOwnershipTest extends TestCase
{
    private array $userIds = [];
    private array $targetIds = [];
    private array $assessmentIds = [];
    private array $findingIds = [];
    private array $roleIds = [];

    protected function tearDown(): void
    {
        if ($this->targetIds !== []) {
            FindingLifecycle::query()
                ->whereIn('target_id', $this->targetIds)
                ->delete();
        }

        if ($this->findingIds !== []) {
            Finding::query()
                ->whereIn('id', $this->findingIds)
                ->delete();
        }

        if ($this->assessmentIds !== []) {
            Assessment::query()
                ->whereIn('id', $this->assessmentIds)
                ->delete();
        }

        if ($this->targetIds !== []) {
            Target::query()
                ->whereIn('id', $this->targetIds)
                ->delete();
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
            User::query()
                ->whereIn('id', $this->userIds)
                ->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = 'Finding Ownership Test';
        $user->email = 'finding-'.Str::uuid().'@example.invalid';
        $user->password = bcrypt(Str::random(64));
        $user->save();

        $this->userIds[] = $user->id;

        return $user;
    }

    private function grantFindingPermissions(User $user): void
    {
        $this->seed(RbacSeeder::class);

        $permissions = Permission::query()
            ->whereIn('slug', [
                'findings.view',
                'findings.manage',
                'findings.confirm',
                'findings.resolve',
            ])
            ->get();

        $this->assertCount(
            4,
            $permissions,
            'Canonical finding permissions are missing.',
        );

        $role = Role::query()->create([
            'name' => 'Finding Ownership Test '.Str::uuid(),
            'slug' => 'finding-ownership-test-'.Str::uuid(),
            'description' =>
                'Temporary finding ownership regression-test role.',
            'is_system' => false,
        ]);

        $this->roleIds[] = $role->id;

        $role->permissions()->attach(
            $permissions->pluck('id')->all()
        );

        $user->roles()->attach($role->id);
    }

    private function makeTarget(User $user): Target
    {
        $target = new Target();
        $target->id = (string) Str::uuid();
        $target->user_id = $user->id;
        $target->name = 'Finding Ownership Target';
        $target->url = 'https://finding-ownership.invalid/';
        $target->hostname = 'finding-ownership.invalid';
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
        Target $target
    ): Assessment {
        $assessment = Assessment::query()->create([
            'user_id' => $user->id,
            'target_id' => $target->id,
            'profile' => 'standard',
            'status' => 'completed',
            'progress' => 100,
            'queued_at' => now()->subMinutes(3),
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'configuration' => [],
            'execution_metadata' => ['test_fixture' => true],
        ]);

        $this->assessmentIds[] = $assessment->id;

        return $assessment;
    }

    private function makeFinding(
        Assessment $assessment,
        Target $target
    ): Finding {
        $finding = Finding::query()->create([
            'assessment_id' => $assessment->id,
            'target_id' => $target->id,
            'type' => 'ownership_test',
            'fingerprint' => hash(
                'sha256',
                'finding-ownership-'.Str::uuid()
            ),
            'title' => 'Finding ownership fixture',
            'description' => 'Synthetic ownership test finding.',
            'severity' => 'medium',
            'confidence' => 'high',
            'evidence' => 'Synthetic fixture only.',
            'evidence_data' => ['test_fixture' => true],
            'remediation' => 'Synthetic fixture.',
            'status' => 'open',
        ]);

        $this->findingIds[] = $finding->id;

        FindingLifecycle::query()->create([
            'target_id' => $target->id,
            'fingerprint' => $finding->fingerprint,
            'type' => $finding->type,
            'title' => $finding->title,
            'severity' => $finding->severity,
            'confidence' => $finding->confidence,
            'status' => 'open',
            'first_seen_at' => now()->subMinute(),
            'last_seen_at' => now(),
            'occurrence_count' => 1,
            'first_assessment_id' => $assessment->id,
            'last_assessment_id' => $assessment->id,
            'last_finding_id' => $finding->id,
        ]);

        return $finding;
    }

    private function makeOwnedFinding(User $owner): Finding
    {
        $target = $this->makeTarget($owner);
        $assessment = $this->makeAssessment($owner, $target);

        return $this->makeFinding($assessment, $target);
    }

    public function test_owner_can_view_own_finding(): void
    {
        $owner = $this->makeUser();
        $this->grantFindingPermissions($owner);

        $finding = $this->makeOwnedFinding($owner);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/findings/{$finding->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $finding->id);
    }

    public function test_other_user_cannot_read_finding_resources(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantFindingPermissions($other);

        $finding = $this->makeOwnedFinding($owner);

        Sanctum::actingAs($other);

        foreach ([
            "/api/v1/findings/{$finding->id}",
            "/api/v1/findings/{$finding->id}/lifecycle",
            "/api/v1/findings/{$finding->id}/history",
            "/api/v1/findings/{$finding->id}/assessments",
            "/api/v1/findings/{$finding->id}/evidence",
            "/api/v1/findings/{$finding->id}/remediation",
        ] as $uri) {
            $this->getJson($uri)->assertNotFound();
        }
    }

    public function test_other_user_cannot_confirm_finding(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantFindingPermissions($other);

        $finding = $this->makeOwnedFinding($owner);

        Sanctum::actingAs($other);

        $this->patchJson(
            "/api/v1/findings/{$finding->id}/confirm"
        )->assertNotFound();

        $this->assertSame(
            'open',
            $finding->fresh()->status
        );
    }

    public function test_other_user_cannot_resolve_finding(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantFindingPermissions($other);

        $finding = $this->makeOwnedFinding($owner);

        Sanctum::actingAs($other);

        $this->patchJson(
            "/api/v1/findings/{$finding->id}/resolve"
        )->assertNotFound();

        $this->assertSame(
            'open',
            $finding->fresh()->status
        );
    }

    public function test_other_user_cannot_change_status_via_generic_endpoint(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();

        $this->grantFindingPermissions($other);

        $finding = $this->makeOwnedFinding($owner);

        Sanctum::actingAs($other);

        $this->patchJson(
            "/api/v1/findings/{$finding->id}/status",
            ['status' => 'confirmed']
        )->assertNotFound();

        $this->assertSame(
            'open',
            $finding->fresh()->status
        );
    }
}
