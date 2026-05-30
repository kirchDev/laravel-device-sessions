<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Support\DeviceSessions;

final class ListUserDevices
{
    /**
     * Active devices for the user, most-recently-seen first. The host decides
     * how to present them (DTO, "is current" flag, icons, ...).
     *
     * @return Collection<int, UserDevice>
     */
    public function execute(Authenticatable $user): Collection
    {
        $deviceModel = DeviceSessions::deviceModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        /** @var Collection<int, UserDevice> $devices */
        $devices = $deviceModel::query()
            ->where($userForeignKey, $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('created_at')
            ->get();

        return $devices;
    }
}
