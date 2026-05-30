<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Models\UserDevice;

interface DeviceResolver
{
    /**
     * Resolve the device for this request (cookie -> bootstrap cache -> new) and
     * persist its current user-agent / OS / masked IP, returning the device row.
     */
    public function resolveOrCreate(Authenticatable $user, Request $request): UserDevice;
}
