<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Models\UserDevice;

it('prunes devices revoked beyond the default retention window', function () {
    $user = makeUser();

    $old = makeDevice($user, 'Old');
    $old->forceFill(['revoked_at' => now()->subDays(200)])->save();

    $recent = makeDevice($user, 'Recent');
    $recent->forceFill(['revoked_at' => now()->subDays(10)])->save();

    makeDevice($user, 'Active');

    $this->artisan('device-sessions:prune')->assertSuccessful();

    expect(UserDevice::count())->toBe(2)
        ->and(UserDevice::whereKey($old->getKey())->exists())->toBeFalse()
        ->and(UserDevice::whereKey($recent->getKey())->exists())->toBeTrue();
});

it('respects the --days option', function () {
    $user = makeUser();

    $device = makeDevice($user, 'Recent');
    $device->forceFill(['revoked_at' => now()->subDays(10)])->save();

    $this->artisan('device-sessions:prune', ['--days' => 5])->assertSuccessful();

    expect(UserDevice::whereKey($device->getKey())->exists())->toBeFalse();
});
