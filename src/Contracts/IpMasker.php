<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

interface IpMasker
{
    /**
     * Reduce an IP address to a privacy-preserving form, or null if it cannot
     * be parsed. Returning the input unchanged disables masking.
     */
    public function mask(?string $ipAddress): ?string;
}
