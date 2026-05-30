<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Contracts\DeviceCookieFactory;
use Symfony\Component\HttpFoundation\Cookie;

final class DeviceCookieBuilder implements DeviceCookieFactory
{
    public function make(Request $request, string $deviceId): Cookie
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('device-sessions.cookie', []);

        $name = $this->stringConfig($config, 'name', 'device');
        $name = $name !== '' ? $name : 'device';

        return cookie(
            $name,
            $deviceId,
            (int) ($config['lifetime'] ?? 60 * 24 * 365),
            $this->stringConfig($config, 'path', '/'),
            null,
            $this->isSecure($request, $config),
            (bool) ($config['http_only'] ?? true),
            false,
            $this->stringConfig($config, 'same_site', 'lax'),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function isSecure(Request $request, array $config): bool
    {
        $secure = $config['secure'] ?? null;

        if (is_bool($secure)) {
            return $secure;
        }

        $sessionSecure = config('session.secure');

        if (is_bool($sessionSecure)) {
            return $sessionSecure;
        }

        return app()->environment('production') || $request->isSecure();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
