<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use KirchDev\DeviceSessions\Contracts\DeviceNameResolver;

final class DefaultDeviceNameResolver implements DeviceNameResolver
{
    public function resolve(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return 'Unknown device';
        }

        $agent = strtolower($userAgent);

        $browser = match (true) {
            str_contains($agent, 'edg/') => 'Edge',
            str_contains($agent, 'chrome/') => 'Chrome',
            str_contains($agent, 'firefox/') => 'Firefox',
            str_contains($agent, 'safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'mac os') || str_contains($agent, 'macintosh') => 'macOS',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios') => 'iOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Unknown OS',
        };

        return $browser.' on '.$platform;
    }
}
