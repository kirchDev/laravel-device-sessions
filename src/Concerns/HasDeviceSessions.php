<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceSessions;

/**
 * Apply to the Authenticatable that owns login devices (typically App\Models\User).
 *
 * The device model is swappable through `device-sessions.models.device`, so the
 * relation is generic in it: an application that overrides the model declares
 * `@use HasDeviceSessions<App\Models\User\UserDevice>` and keeps its own type
 * across `devices()` and `currentDevice()`. Omitting the argument falls back to
 * the packaged model, which is what a consumer that does not override one wants.
 *
 * @template TDeviceModel of UserDevice = UserDevice
 *
 * @phpstan-require-extends Model
 */
trait HasDeviceSessions
{
    /**
     * @return HasMany<TDeviceModel, $this>
     */
    public function devices(): HasMany
    {
        /** @var class-string<TDeviceModel> $deviceModel */
        $deviceModel = config('device-sessions.models.device', UserDevice::class);
        $foreignKey = config('device-sessions.column_names.user_foreign_key', 'user_id');

        return $this->hasMany($deviceModel, $foreignKey);
    }

    /**
     * The current device id for the given (or container) request — the
     * `current_device_id` attribute set by the middleware, else the device cookie.
     */
    public function currentDeviceId(?Request $request = null): ?string
    {
        return DeviceSessions::currentDeviceId($request);
    }

    /**
     * The current active device for the given (or container) request, if any.
     *
     * @return TDeviceModel|null
     */
    public function currentDevice(?Request $request = null): ?UserDevice
    {
        $deviceId = $this->currentDeviceId($request);

        if ($deviceId === null) {
            return null;
        }

        return $this->devices()
            ->whereKey($deviceId)
            ->whereNull('revoked_at')
            ->first();
    }
}
