<?php

namespace App\Services\Security;

use App\Models\Target;
use App\Models\User;
use RuntimeException;

final class AuthorizedTargetGuard
{
    public function assertAccessible(
        User $user,
        Target $target
    ): void {
        if ((string) $target->user_id !== (string) $user->id) {
            throw new RuntimeException(
                'Target does not belong to this user.'
            );
        }

        if (
            ! $target->authorization_confirmed ||
            $target->status !== 'active'
        ) {
            throw new RuntimeException(
                'Target is not authorized or active.'
            );
        }
    }
}
