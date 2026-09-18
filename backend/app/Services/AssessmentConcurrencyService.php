<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\DB;

class AssessmentConcurrencyService
{
    /*
     * PostgreSQL transaction advisory lock namespace dedicated to
     * assessment concurrency admission.
     */
    private const ADVISORY_LOCK_KEY = 481516234;

    public function hasCapacity(): bool
    {
        $driver = DB::getDriverName();

        /*
         * PostgreSQL production path:
         * serialize admission across multiple workers using a
         * transaction-level advisory lock.
         */
        if ($driver === 'pgsql') {
            DB::select(
                'SELECT pg_advisory_xact_lock(?)',
                [self::ADVISORY_LOCK_KEY]
            );
        }

        /*
         * SQLite fallback:
         *
         * CrypticX Lab's current lightweight deployment runs exactly one
         * queue worker, so execution is already serialized at worker level.
         *
         * Do NOT scale to multiple queue workers while SQLite is in use.
         * Multi-worker production deployments must use PostgreSQL so the
         * advisory lock above can provide cross-worker admission safety.
         */
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new \RuntimeException(
                "Unsupported database driver for assessment concurrency: {$driver}"
            );
        }

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
