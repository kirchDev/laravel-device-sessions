<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use KirchDev\DeviceSessions\Contracts\IpMasker;

/**
 * Data-minimising IP masker.
 *
 * IPv4 is truncated to /24 (last octet zeroed) — the widely-accepted analytics
 * anonymisation. IPv6 is truncated to /48 rather than /64, because a /64 is
 * typically a single subscriber and offers weaker anonymisation.
 */
final class DefaultIpMasker implements IpMasker
{
    public function mask(?string $ipAddress): ?string
    {
        if ($ipAddress === null || trim($ipAddress) === '') {
            return null;
        }

        $packed = @inet_pton(trim($ipAddress));

        if ($packed === false) {
            return null;
        }

        $masked = match (strlen($packed)) {
            4 => substr($packed, 0, 3)."\0",
            16 => substr($packed, 0, 6).str_repeat("\0", 10),
            default => null,
        };

        if ($masked === null) {
            return null;
        }

        $result = @inet_ntop($masked);

        return $result === false ? null : $result;
    }
}
