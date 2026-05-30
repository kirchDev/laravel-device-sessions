<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use KirchDev\DeviceSessions\Contracts\RememberTokenHasher;

final class Sha256RememberTokenHasher implements RememberTokenHasher
{
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
