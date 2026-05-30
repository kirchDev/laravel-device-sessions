<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Integrations\Fortify;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Contracts\DeviceCookieFactory;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Throwable;

/**
 * Bridges Fortify's two-factor interruption: the normal Login event hasn't
 * fired yet at the challenge point, so the device cookie wouldn't be set and
 * the remember token couldn't bind afterwards. This resolves the device and
 * queues the cookie when the challenge is issued.
 *
 * Auto-registered by the service provider only when Fortify is installed
 * (class_exists). Hosts using a different 2FA solution can write their own
 * bridge against the DeviceResolver contract.
 */
final class QueueDeviceCookieOnTwoFactorChallenge
{
    public function __construct(
        private readonly DeviceResolver $deviceResolver,
        private readonly DeviceCookieFactory $cookieFactory,
    ) {}

    public function handle(TwoFactorAuthenticationChallenged $event): void
    {
        $user = $event->user;

        if (! $user instanceof Authenticatable) {
            return;
        }

        try {
            $request = request();
        } catch (Throwable) {
            return;
        }

        if (! $request instanceof Request) {
            return;
        }

        $device = $this->deviceResolver->resolveOrCreate($user, $request);

        Cookie::queue($this->cookieFactory->make($request, (string) $device->getKey()));
    }
}
