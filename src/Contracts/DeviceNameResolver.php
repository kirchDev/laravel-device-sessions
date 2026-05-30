<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

interface DeviceNameResolver
{
    /**
     * Build a human-friendly default name for a device from its User-Agent
     * (e.g. "Chrome on Windows").
     */
    public function resolve(?string $userAgent): string;
}
