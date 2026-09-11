<?php

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Exceptions\BillingUnavailableException;
use App\Models\User;

class NullBillingGateway implements BillingGateway
{
    public function providerCode(): string
    {
        return 'none';
    }

    public function configured(): bool
    {
        return false;
    }

    public function createCheckout(
        User $user,
        string $planCode,
    ): array {
        throw new BillingUnavailableException(
            'Online billing is not configured yet.'
        );
    }
}
