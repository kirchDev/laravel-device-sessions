<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KirchDev\DeviceSessions\Models\UserDevice;

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
}
