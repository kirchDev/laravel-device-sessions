<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

interface RememberTokenHasher
{
    /**
     * Hash a raw remember-me token for at-rest storage and lookup. Must be
     * deterministic so a presented token can be matched against the stored hash.
     */
    public function hash(string $token): string;
}
