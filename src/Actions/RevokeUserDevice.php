<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class RevokeUserDevice
{
    /**
     * Revoke a single device (and its active remember tokens). Returns false if
     * the device does not belong to the user or is already revoked.
     */
    public function execute(Authenticatable $user, string $deviceId): bool
    {
        $deviceModel = DeviceSessions::deviceModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        $device = $deviceModel::query()
            ->whereKey($deviceId)
            ->where($userForeignKey, $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->first();

        if (! $device instanceof UserDevice) {
            return false;
        }

        $now = now();

        $device->forceFill(['revoked_at' => $now])->save();

        $device->rememberTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        return true;
    }
}
