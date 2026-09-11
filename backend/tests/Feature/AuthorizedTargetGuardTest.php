<?php

namespace Tests\Feature;

use App\Models\Target;
use App\Models\User;
use App\Services\Security\AuthorizedTargetGuard;
use RuntimeException;
use Tests\TestCase;

class AuthorizedTargetGuardTest extends TestCase
{
    private function user(
        string $id =
            '11111111-1111-4111-8111-111111111111'
    ): User {
        $user = new User();

        $user->forceFill([
            'id' => $id,
            'name' => 'Security fixture',
            'email' => 'fixture@example.test',
        ]);

        return $user;
    }

    private function target(
        array $overrides = []
    ): Target {
        $target = new Target();

        $target->forceFill(array_merge([
            'id' =>
                '22222222-2222-4222-8222-222222222222',
            'user_id' =>
                '11111111-1111-4111-8111-111111111111',
            'authorization_confirmed' => true,
            'status' => 'active',
        ], $overrides));

        return $target;
    }

    public function test_owned_authorized_active_target_is_accepted(): void
    {
        $guard = new AuthorizedTargetGuard();

        $guard->assertAccessible(
            $this->user(),
            $this->target()
        );

        $this->assertTrue(true);
    }

    public function test_foreign_target_is_rejected(): void
    {
        $guard = new AuthorizedTargetGuard();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Target does not belong to this user.'
        );

        $guard->assertAccessible(
            $this->user(),
            $this->target([
                'user_id' =>
                    '33333333-3333-4333-8333-333333333333',
            ])
        );
    }

    public function test_unconfirmed_target_is_rejected(): void
    {
        $guard = new AuthorizedTargetGuard();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Target is not authorized or active.'
        );

        $guard->assertAccessible(
            $this->user(),
            $this->target([
                'authorization_confirmed' => false,
            ])
        );
    }

    public function test_inactive_target_is_rejected(): void
    {
        $guard = new AuthorizedTargetGuard();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Target is not authorized or active.'
        );

        $guard->assertAccessible(
            $this->user(),
            $this->target([
                'status' => 'inactive',
            ])
        );
    }
}
