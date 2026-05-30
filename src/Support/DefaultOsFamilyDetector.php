<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use KirchDev\DeviceSessions\Contracts\OsFamilyDetector;
use KirchDev\DeviceSessions\Enums\DeviceOsFamily;

final class DefaultOsFamilyDetector implements OsFamilyDetector
{
    public function detect(?string $userAgent): DeviceOsFamily
    {
        return DeviceOsFamily::fromUserAgent($userAgent);
    }
}
