<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Concurrent Assessments
    |--------------------------------------------------------------------------
    |
    | Platform-wide hard safety ceiling for assessments executing through
    | scanner workers at the same time.
    |
    | This is infrastructure capacity, not a Free/Premium entitlement.
    |
    */

    'max_concurrent_assessments' => max(
        1,
        (int) env('SCANNER_MAX_CONCURRENT_ASSESSMENTS', 1)
    ),


    /*
    |--------------------------------------------------------------------------
    | Scanner Execution Runtime
    |--------------------------------------------------------------------------
    |
    | "process" is the development/runtime compatibility mode.
    | "container" is the hardened production scanner boundary.
    |
    | Container mode is fail-closed. There is no automatic fallback to the
    | host process runtime when Docker/container execution is unavailable.
    |
    */

    'runtime' => env('SCANNER_RUNTIME', 'process'),

    'container' => [
        'image' => env(
            'SCANNER_CONTAINER_IMAGE',
            'crypticx/scanner-runtime:local'
        ),
        'network' => env(
            'SCANNER_CONTAINER_NETWORK',
            'bridge'
        ),
        'memory' => env(
            'SCANNER_CONTAINER_MEMORY',
            '256m'
        ),
        'cpus' => env(
            'SCANNER_CONTAINER_CPUS',
            '0.50'
        ),
        'pids_limit' => env(
            'SCANNER_CONTAINER_PIDS_LIMIT',
            64
        ),
    ],

];
