<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

/**
 * Builds namespaced cache keys for the package, prefixed by
 * config('device-sessions.cache.key_prefix').
 */
final class DeviceCacheKey
{
    public const LAST_SEEN = 'last_seen';

    public const BOOTSTRAP = 'bootstrap';

    public static function scoped(string $key, string ...$segments): string
    {
        $prefix = config('device-sessions.cache.key_prefix', 'device_sessions');
        $prefix = is_string($prefix) ? $prefix : 'device_sessions';

        $parts = array_filter(
            [$prefix, $key, ...$segments],
            static fn (string $part): bool => $part !== '',
        );

        return implode(':', $parts);
    }
}
