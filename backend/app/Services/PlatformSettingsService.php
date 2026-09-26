<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;

class PlatformSettingsService
{
    public const DEFAULTS = [
        'public_registration_enabled' => true,
        'assessment_creation_enabled' => true,

        /*
         * Keep disabled until the frontend device-enrollment flow is
         * regression-locked. The middleware is fail-closed once enabled.
         */
        'trusted_device_admin_enforcement' => false,

        /*
         * Commercial controls.
         *
         * Premium starts disabled for the new production site.
         * When disabled, commercial numeric quotas are unlimited.
         * Security boundaries and capability authorization remain enforced.
         */
        'premium_enabled' => false,

        'free_targets_total' => 3,
        'free_assessments_monthly' => 25,
        'free_reports_monthly' => 5,
        'free_monitoring_policies' => 0,
        'free_concurrent_assessments' => 1,

        'professional_targets_total' => 50,
        'professional_assessments_monthly' => 1000,
        'professional_reports_monthly' => 250,
        'professional_monitoring_policies' => 10,
        'professional_concurrent_assessments' => 2,

        'team_targets_total' => 250,
        'team_assessments_monthly' => 5000,
        'team_reports_monthly' => 2000,
        'team_monitoring_policies' => 100,
        'team_concurrent_assessments' => 5,
    ];

    public function all(): array
    {
        $stored = PlatformSetting::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->get()
            ->keyBy('key');

        $result = [];

        foreach (self::DEFAULTS as $key => $default) {
            $result[$key] = $stored->has($key)
                ? $stored[$key]->value
                : $default;
        }

        return $result;
    }

    public function boolean(
        string $key,
        bool $default = false,
    ): bool {
        $setting = PlatformSetting::query()
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return array_key_exists($key, self::DEFAULTS)
                ? (bool) self::DEFAULTS[$key]
                : $default;
        }

        return (bool) $setting->value;
    }

    public function integer(
        string $key,
        ?int $default = null,
    ): ?int {
        $setting = PlatformSetting::query()
            ->where('key', $key)
            ->first();

        $value = $setting
            ? $setting->value
            : (self::DEFAULTS[$key] ?? $default);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        return max(0, (int) $value);
    }

    public function update(
        string $key,
        bool|int $value,
        User $updatedBy,
    ): PlatformSetting {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException(
                'Unsupported platform setting.'
            );
        }

        $default = self::DEFAULTS[$key];

        if (is_bool($default)) {
            $value = (bool) $value;
        } elseif (is_int($default)) {
            $value = max(0, (int) $value);
        }

        return PlatformSetting::query()
            ->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_by' => $updatedBy->id,
                ],
            );
    }
}
