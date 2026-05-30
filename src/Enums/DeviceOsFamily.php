<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Enums;

enum DeviceOsFamily: string
{
    case Android = 'android';
    case IOS = 'ios';
    case Linux = 'linux';
    case MacOS = 'macos';
    case Unknown = 'unknown';
    case Windows = 'windows';

    /**
     * Best-effort OS family from a raw User-Agent string.
     *
     * Presentation (icons, labels) is intentionally left to the host — this
     * only classifies the platform.
     */
    public static function fromUserAgent(?string $userAgent): self
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return self::Unknown;
        }

        $agent = strtolower($userAgent);

        return match (true) {
            str_contains($agent, 'android') => self::Android,
            str_contains($agent, 'iphone'),
            str_contains($agent, 'ipad'),
            str_contains($agent, 'ios') => self::IOS,
            str_contains($agent, 'windows') => self::Windows,
            str_contains($agent, 'mac os'),
            str_contains($agent, 'macintosh') => self::MacOS,
            str_contains($agent, 'linux') => self::Linux,
            default => self::Unknown,
        };
    }
}
