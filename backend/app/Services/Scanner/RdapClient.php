<?php

namespace App\Services\Scanner;

interface RdapClient
{
    public function lookupDomain(
        string $hostname
    ): array;
}
