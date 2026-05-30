<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class RevokeOtherUserDevices
{
    /**
     * Revoke every active device for the user except the current one (and their
     * active remember tokens). A null/empty current id is a no-op.
     */
    public function execute(Authenticatable $user, ?string $currentDeviceId): void
    {
        if ($currentDeviceId === null || $currentDeviceId === '') {
            return;
        }

        $deviceModel = DeviceSessions::deviceModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        $devices = $deviceModel::query()
            ->where($userForeignKey, $user->getAuthIdentifier())
            ->whereKeyNot($currentDeviceId)
            ->whereNull('revoked_at')
            ->get();

        $now = now();

        foreach ($devices as $device) {
            $device->forceFill(['revoked_at' => $now])->save();

            $device->rememberTokens()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);
        }
    }
}
