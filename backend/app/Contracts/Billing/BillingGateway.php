<?php

namespace App\Contracts\Billing;

use App\Models\User;

interface BillingGateway
{
    public function providerCode(): string;

    public function configured(): bool;

    /**
     * Creates a provider-side transaction using trusted
     * server-side user and plan metadata.
     *
     * @return array{
     *   transaction_id:string,
     *   provider:string
     * }
     */
    public function createCheckout(
        User $user,
        string $planCode,
    ): array;
}
