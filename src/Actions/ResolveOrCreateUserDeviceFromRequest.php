<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Contracts\DeviceNameResolver;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Contracts\IpMasker;
use KirchDev\DeviceSessions\Contracts\OsFamilyDetector;
use KirchDev\DeviceSessions\Enums\DeviceType;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceCacheKey;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class ResolveOrCreateUserDeviceFromRequest implements DeviceResolver
{
    public function __construct(
        private readonly DeviceNameResolver $deviceNameResolver,
        private readonly OsFamilyDetector $osFamilyDetector,
        private readonly IpMasker $ipMasker,
    ) {}

    public function resolveOrCreate(Authenticatable $user, Request $request): UserDevice
    {
        $deviceModel = DeviceSessions::deviceModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        $cookieValue = $request->cookie(DeviceSessions::cookieName());
        $deviceId = is_string($cookieValue) ? trim($cookieValue) : '';

        $userAgent = $request->userAgent();
        $osFamily = $this->osFamilyDetector->detect($userAgent);
        $maskedIp = $this->ipMasker->mask($request->ip());
        $bootstrapCacheKey = $this->resolveBootstrapCacheKey($user, $request);

        $device = null;

        if ($deviceId !== '') {
            $device = $deviceModel::query()
                ->whereKey($deviceId)
                ->where($userForeignKey, $user->getAuthIdentifier())
                ->where('type', DeviceType::Web)
                ->whereNull('revoked_at')
                ->first();
        }

        if (! $device instanceof UserDevice && $bootstrapCacheKey !== null) {
            $cachedDeviceId = DeviceSessions::cache()->get($bootstrapCacheKey);

            if (is_string($cachedDeviceId) && trim($cachedDeviceId) !== '') {
                $device = $deviceModel::query()
                    ->whereKey(trim($cachedDeviceId))
                    ->where($userForeignKey, $user->getAuthIdentifier())
                    ->where('type', DeviceType::Web)
                    ->whereNull('revoked_at')
                    ->first();
            }
        }

        if (! $device instanceof UserDevice) {
            $device = new $deviceModel([
                $userForeignKey => $user->getAuthIdentifier(),
                'type' => DeviceType::Web,
                'name' => $this->deviceNameResolver->resolve($userAgent),
            ]);
        }

        $device->os_family = $osFamily;
        $device->user_agent = $userAgent;
        $device->ip_address = $maskedIp;

        $device->save();

        if ($bootstrapCacheKey !== null) {
            DeviceSessions::cache()->put(
                $bootstrapCacheKey,
                (string) $device->getKey(),
                $this->bootstrapTtl(),
            );
        }

        return $device;
    }

    private function resolveBootstrapCacheKey(Authenticatable $user, Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = trim((string) $request->session()->getId());

        if ($sessionId === '') {
            return null;
        }

        return DeviceCacheKey::scoped(
            DeviceCacheKey::BOOTSTRAP,
            (string) $user->getAuthIdentifier(),
            $sessionId,
        );
    }

    private function bootstrapTtl(): int
    {
        $ttl = config('device-sessions.cache.bootstrap_ttl', 60);

        return is_numeric($ttl) ? (int) $ttl : 60;
    }
}
