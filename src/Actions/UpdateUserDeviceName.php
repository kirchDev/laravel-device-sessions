<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class UpdateUserDeviceName
{
    /**
     * Rename an active device the user owns. Returns null if no such device.
     */
    public function execute(Authenticatable $user, string $deviceId, string $name): ?UserDevice
    {
        $deviceModel = DeviceSessions::deviceModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        $device = $deviceModel::query()
            ->whereNull('revoked_at')
            ->whereKey($deviceId)
            ->where($userForeignKey, $user->getAuthIdentifier())
            ->first();

        if (! $device instanceof UserDevice) {
            return null;
        }

        $device->forceFill(['name' => trim($name)])->save();

        return $device->refresh();
    }
}
