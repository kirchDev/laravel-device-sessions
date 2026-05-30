<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Listeners;

use Illuminate\Auth\Events\OtherDeviceLogout;
use KirchDev\DeviceSessions\Actions\RevokeOtherUserDevices;
use KirchDev\DeviceSessions\Support\DeviceSessions;

/**
 * Mirrors Laravel's "log out other devices" into device-session land: when
 * Auth::logoutOtherDevices() fires OtherDeviceLogout, revoke every device for
 * the user except the current one. Opt-in via config.
 */
final class RevokeDevicesOnOtherDeviceLogout
{
    public function __construct(
        private readonly RevokeOtherUserDevices $revokeOtherUserDevices,
    ) {}

    public function handle(OtherDeviceLogout $event): void
    {
        $this->revokeOtherUserDevices->execute($event->user, DeviceSessions::currentDeviceId());
    }
}
