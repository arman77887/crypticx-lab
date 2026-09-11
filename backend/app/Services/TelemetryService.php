<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class TelemetryService
{
    private const REDACTED = '[REDACTED]';

    private const TRUNCATED = '[TRUNCATED]';

    private const MAX_METADATA_DEPTH = 8;

    private const MAX_METADATA_STRING_LENGTH = 4096;

    public function audit(
        Request $request,
        string $action,
        string $category,
        ?User $user = null,
        array $metadata = [],
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' =>
                $user?->id
                ?? $request->user()?->id,

            'action' => $action,
            'category' => $category,
            'method' => $request->method(),
            'route' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,

            'metadata' =>
                $this->sanitizeMetadata(
                    $metadata,
                ),

            'created_at' => now(),
        ]);
    }

    public function activity(
        Request $request,
        string $eventType,
        ?User $user = null,
        array $metadata = [],
    ): ActivityEvent {
        return ActivityEvent::create([
            'user_id' =>
                $user?->id
                ?? $request->user()?->id,

            'event_type' => $eventType,
            'ip_address' => $request->ip(),

            'device_type' =>
                $this->deviceType(
                    $request->userAgent(),
                ),

            'browser' =>
                $this->browser(
                    $request->userAgent(),
                ),

            'platform' =>
                $this->platform(
                    $request->userAgent(),
                ),

            'metadata' =>
                $this->sanitizeMetadata(
                    $metadata,
                ),

            'created_at' => now(),
        ]);
    }

    public function sanitizeMetadata(
        array $metadata,
    ): array {
        $result = $this->sanitizeValue(
            $metadata,
            0,
        );

        return is_array($result)
            ? $result
            : [];
    }

    private function sanitizeValue(
        mixed $value,
        int $depth,
    ): mixed {
        if ($depth >= self::MAX_METADATA_DEPTH) {
            return self::TRUNCATED;
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                if (
                    is_string($key)
                    && $this->isSensitiveKey($key)
                ) {
                    $clean[$key] =
                        self::REDACTED;

                    continue;
                }

                $clean[$key] =
                    $this->sanitizeValue(
                        $item,
                        $depth + 1,
                    );
            }

            return $clean;
        }

        if (is_string($value)) {
            /*
             * Do not persist an accidental full bearer credential even
             * when a caller used a non-sensitive metadata key.
             */
            if (
                preg_match(
                    '/^\s*Bearer\s+[^\s]+$/i',
                    $value,
                ) === 1
            ) {
                return self::REDACTED;
            }

            if (
                strlen($value)
                > self::MAX_METADATA_STRING_LENGTH
            ) {
                return substr(
                    $value,
                    0,
                    self::MAX_METADATA_STRING_LENGTH,
                ).self::TRUNCATED;
            }
        }

        return $value;
    }

    private function isSensitiveKey(
        string $key,
    ): bool {
        $normalized = strtolower(
            trim($key),
        );

        return preg_match(
            '/(^|[._-])(?:password|passwd|passphrase|secret|token|access_token|refresh_token|device_token|authorization|cookie|set-cookie|api_key|apikey|client_secret|private_key)($|[._-])/i',
            $normalized,
        ) === 1;
    }

    private function deviceType(
        ?string $userAgent,
    ): string {
        if (! $userAgent) {
            return 'unknown';
        }

        return preg_match(
            '/Mobile|Android|iPhone|iPad/i',
            $userAgent,
        )
            ? 'mobile'
            : 'desktop';
    }

    private function browser(
        ?string $userAgent,
    ): string {
        if (! $userAgent) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Edg/i', $userAgent)
                === 1 => 'Edge',

            preg_match('/Chrome/i', $userAgent)
                === 1 => 'Chrome',

            preg_match('/Firefox/i', $userAgent)
                === 1 => 'Firefox',

            preg_match('/Safari/i', $userAgent)
                === 1 => 'Safari',

            default => 'Other',
        };
    }

    private function platform(
        ?string $userAgent,
    ): string {
        if (! $userAgent) {
            return 'unknown';
        }

        return match (true) {
            preg_match('/Windows/i', $userAgent)
                === 1 => 'Windows',

            preg_match('/Android/i', $userAgent)
                === 1 => 'Android',

            preg_match(
                '/iPhone|iPad|iOS/i',
                $userAgent,
            ) === 1 => 'iOS',

            preg_match('/Mac OS X/i', $userAgent)
                === 1 => 'macOS',

            preg_match('/Linux/i', $userAgent)
                === 1 => 'Linux',

            default => 'Other',
        };
    }
}
