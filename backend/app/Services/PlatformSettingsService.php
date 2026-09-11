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

    public function update(
        string $key,
        bool $value,
        User $updatedBy,
    ): PlatformSetting {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException(
                'Unsupported platform setting.'
            );
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
