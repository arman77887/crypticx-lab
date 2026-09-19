<?php

namespace App\Services;

use App\Jobs\SendMonitoringDailyDigest;
use App\Models\MonitoringDailyDigestDelivery;
use App\Models\MonitoringNotificationPreference;
use App\Models\MonitoringPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MonitoringDailyDigestPlannerService
{
    public function __construct(
        private MonitoringDailyDigestBuilderService $builder,
    ) {
    }

    public function planDue(): array
    {
        $result = [
            'evaluated' => 0,
            'created' => 0,
            'existing' => 0,
            'disabled' => 0,
            'not_due' => 0,
            'invalid_recipient' => 0,
        ];

        /*
         * Only users with at least one enabled monitoring policy
         * are candidates for a monitoring digest.
         */
        $userIds = MonitoringPolicy::query()
            ->where('enabled', true)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $result['evaluated']++;

            $outcome = $this->planUser(
                (string) $userId
            );

            if (array_key_exists($outcome, $result)) {
                $result[$outcome]++;
            }
        }

        return $result;
    }

    public function planUser(string $userId): string
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return 'disabled';
        }

        $preference =
            MonitoringNotificationPreference::query()
                ->where('user_id', $user->id)
                ->first();

        /*
         * Existing users without an explicit preference retain
         * the platform default: daily digest enabled at 08:00 UTC.
         */
        $enabled =
            $preference?->daily_digest_enabled ?? true;

        if (! $enabled) {
            return 'disabled';
        }

        $timezone = trim(
            (string) (
                $preference?->daily_digest_timezone
                ?? 'UTC'
            )
        );

        if (
            $timezone === '' ||
            ! in_array(
                $timezone,
                DateTimeZone::listIdentifiers(),
                true
            )
        ) {
            /*
             * Invalid preference must never silently shift the
             * user's reporting window to another timezone.
             */
            return 'disabled';
        }

        $hour = (int) (
            $preference?->daily_digest_hour ?? 8
        );

        if ($hour < 0 || $hour > 23) {
            return 'disabled';
        }

        $localNow = CarbonImmutable::now($timezone);

        if ($localNow->hour < $hour) {
            return 'not_due';
        }

        $recipient = trim((string) $user->email);

        if (
            $recipient === '' ||
            filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return 'invalid_recipient';
        }

        /*
         * Daily digest covers the previous complete local
         * calendar day. This prevents scans later today from
         * being omitted after the morning digest is created.
         */
        $digestDate = $localNow
            ->subDay()
            ->toDateString();

        return DB::transaction(function () use (
            $user,
            $timezone,
            $recipient,
            $digestDate,
        ): string {
            /*
             * Re-check inside the transaction. The unique database
             * constraint is the final idempotency boundary.
             */
            $existing =
                MonitoringDailyDigestDelivery::query()
                    ->where('user_id', $user->id)
                    ->whereDate('digest_date', $digestDate)
                    ->first();

            if ($existing) {
                return 'existing';
            }

            $snapshot = $this->builder->build(
                $user,
                $digestDate,
                $timezone
            );

            try {
                $delivery =
                    MonitoringDailyDigestDelivery::query()
                        ->create([
                            'user_id' => $user->id,
                            'digest_date' => $digestDate,
                            'timezone' => $timezone,
                            'recipient' => $recipient,
                            'status' => 'pending',
                            'payload' => $snapshot,
                            'attempt_count' => 0,
                        ]);

                DB::afterCommit(
                    fn () => SendMonitoringDailyDigest::dispatch(
                        $delivery->id
                    )
                );
            } catch (\Illuminate\Database\QueryException $e) {
                /*
                 * Concurrent schedulers may race between the read
                 * and insert. Treat only an actually-existing
                 * user/date delivery as the idempotent winner.
                 */
                $exists =
                    MonitoringDailyDigestDelivery::query()
                        ->where('user_id', $user->id)
                        ->whereDate(
                            'digest_date',
                            $digestDate
                        )
                        ->exists();

                if ($exists) {
                    return 'existing';
                }

                throw $e;
            }

            return 'created';
        });
    }
}
