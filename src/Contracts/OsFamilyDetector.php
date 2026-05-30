<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

use KirchDev\DeviceSessions\Enums\DeviceOsFamily;

interface OsFamilyDetector
{
    /**
     * Classify the operating system family from a raw User-Agent string.
     */
    public function detect(?string $userAgent): DeviceOsFamily;
}
