<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use KirchDev\DeviceSessions\Models\UserDevice;

/**
 * Fired when a device's last-seen timestamp is actually written (i.e. not on
 * throttled hits). Hosts may listen to, for example, touch the user's own
 * last-seen column without the package assuming such a column exists.
 */
final class DeviceTouched
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly UserDevice $device,
    ) {}
}
