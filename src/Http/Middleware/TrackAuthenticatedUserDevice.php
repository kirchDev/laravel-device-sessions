<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Actions\TouchDeviceLastSeen;
use KirchDev\DeviceSessions\Contracts\DeviceCookieFactory;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Support\DeviceSessions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves (and freshens) the authenticated user's device per request, throttled
 * last-seen touch included, and exposes the device id as the
 * `current_device_id` request attribute. Not auto-registered — alias it (e.g.
 * 'track.device') and attach it to your authenticated routes.
 */
class TrackAuthenticatedUserDevice
{
    public function __construct(
        private readonly DeviceResolver $deviceResolver,
        private readonly TouchDeviceLastSeen $touchDeviceLastSeen,
        private readonly DeviceCookieFactory $cookieFactory,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Authenticatable) {
            $deviceCookie = $this->resolveDeviceCookie($request);

            $device = $this->deviceResolver->resolveOrCreate($user, $request);
            $this->touchDeviceLastSeen->execute($user, $device);

            $resolvedDeviceId = (string) $device->getKey();
            $request->attributes->set('current_device_id', $resolvedDeviceId);

            if ($deviceCookie !== $resolvedDeviceId) {
                Cookie::queue($this->cookieFactory->make($request, $resolvedDeviceId));
            }
        }

        return $next($request);
    }

    private function resolveDeviceCookie(Request $request): ?string
    {
        $cookie = $request->cookie(DeviceSessions::cookieName());

        return is_string($cookie) && trim($cookie) !== '' ? trim($cookie) : null;
    }
}
