<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Models\UserDeviceRememberToken;

/**
 * Central resolver for the package's configurable models, keys, and cache store.
 * Keeps config reads (and their class-string narrowing) in one place.
 */
final class DeviceSessions
{
    /**
     * @return class-string<UserDevice>
     */
    public static function deviceModel(): string
    {
        $model = config('device-sessions.models.device', UserDevice::class);

        /** @var class-string<UserDevice> $resolved */
        $resolved = is_string($model) && $model !== '' ? $model : UserDevice::class;

        return $resolved;
    }

    /**
     * @return class-string<UserDeviceRememberToken>
     */
    public static function rememberTokenModel(): string
    {
        $model = config('device-sessions.models.remember_token', UserDeviceRememberToken::class);

        /** @var class-string<UserDeviceRememberToken> $resolved */
        $resolved = is_string($model) && $model !== '' ? $model : UserDeviceRememberToken::class;

        return $resolved;
    }

    /**
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        $model = config('device-sessions.models.user') ?: config('auth.providers.users.model');

        /** @var class-string<Model> $resolved */
        $resolved = is_string($model) && $model !== '' ? $model : 'App\\Models\\User';

        return $resolved;
    }

    public static function userForeignKey(): string
    {
        $key = config('device-sessions.column_names.user_foreign_key', 'user_id');

        return is_string($key) && $key !== '' ? $key : 'user_id';
    }

    public static function cookieName(): string
    {
        $name = config('device-sessions.cookie.name', 'device');

        return is_string($name) && $name !== '' ? $name : 'device';
    }

    public static function cache(): Repository
    {
        $store = config('device-sessions.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }
}
