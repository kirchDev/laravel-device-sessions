<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

interface DeviceCookieFactory
{
    /**
     * Build the long-lived cookie that binds this browser to a device row.
     */
    public function make(Request $request, string $deviceId): Cookie;
}
