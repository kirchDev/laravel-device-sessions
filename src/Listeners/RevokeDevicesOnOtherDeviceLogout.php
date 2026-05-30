<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Listeners;

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Actions\RevokeOtherUserDevices;
use KirchDev\DeviceSessions\Support\DeviceSessions;
use Throwable;

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
        $this->revokeOtherUserDevices->execute($event->user, $this->resolveCurrentDeviceId());
    }

    private function resolveCurrentDeviceId(): ?string
    {
        try {
            $request = request();
        } catch (Throwable) {
            return null;
        }

        if (! $request instanceof Request) {
            return null;
        }

        $attribute = $request->attributes->get('current_device_id');

        if (is_string($attribute) && $attribute !== '') {
            return $attribute;
        }

        $cookie = $request->cookie(DeviceSessions::cookieName());

        return is_string($cookie) && trim($cookie) !== '' ? trim($cookie) : null;
    }
}
