<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use KirchDev\DeviceSessions\Events\DeviceTouched;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceCacheKey;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class TouchDeviceLastSeen
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function execute(Authenticatable $user, UserDevice $device): void
    {
        $cacheKey = DeviceCacheKey::scoped(
            DeviceCacheKey::LAST_SEEN,
            (string) $user->getAuthIdentifier(),
            (string) $device->getKey(),
        );

        $cache = DeviceSessions::cache();

        if ($cache->has($cacheKey)) {
            return;
        }

        $now = now();

        $device->forceFill([
            'last_seen_at' => $now,
            'last_used_at' => $now,
        ])->save();

        $this->events->dispatch(new DeviceTouched($user, $device));

        $cache->put($cacheKey, true, $now->copy()->addSeconds($this->throttleSeconds()));
    }

    private function throttleSeconds(): int
    {
        $throttle = config('device-sessions.cache.last_seen_throttle', 60);

        return is_numeric($throttle) ? (int) $throttle : 60;
    }
}
