<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentConcurrencyService
{
    /*
     * PostgreSQL transaction advisory lock namespace dedicated to
     * assessment concurrency admission.
     *
     * Every worker attempting queued -> running admission serializes
     * through this lock before counting current running assessments.
     */
    private const ADVISORY_LOCK_KEY = 481516234;

    public function hasCapacity(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Assessment concurrency admission requires PostgreSQL.'
            );
        }

        /*
         * The caller must already be inside a database transaction.
         * pg_advisory_xact_lock is automatically released when that
         * transaction commits or rolls back.
         */
        DB::select(
            'SELECT pg_advisory_xact_lock(?)',
            [self::ADVISORY_LOCK_KEY]
        );

        $running = Assessment::query()
            ->where('status', 'running')
            ->count();

        $limit = max(
            1,
            (int) config('scanner.max_concurrent_assessments', 1)
        );

        return $running < $limit;
    }
}
