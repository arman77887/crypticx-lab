<?php

namespace App\Services\Scanner;

interface ScannerExecutionRuntime
{
    public function scan(array $request): array;
}
