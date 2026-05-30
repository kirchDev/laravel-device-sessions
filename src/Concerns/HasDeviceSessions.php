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
 * @phpstan-require-extends Model
 */
trait HasDeviceSessions
{
    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        /** @var class-string<UserDevice> $deviceModel */
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
