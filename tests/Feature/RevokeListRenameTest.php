<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Actions\ListUserDevices;
use KirchDev\DeviceSessions\Actions\RevokeOtherUserDevices;
use KirchDev\DeviceSessions\Actions\RevokeUserDevice;
use KirchDev\DeviceSessions\Actions\UpdateUserDeviceName;
use KirchDev\DeviceSessions\Tests\Fixtures\User;

it('revokes a single device and its active tokens', function () {
    $user = makeUser();
    $device = makeDevice($user);

    $result = app(RevokeUserDevice::class)->execute($user, (string) $device->getKey());

    expect($result)->toBeTrue();

    $device->refresh();

    expect($device->revoked_at)->not->toBeNull()
        ->and($device->rememberTokens()->whereNull('revoked_at')->count())->toBe(0);
});

it('returns false when revoking a device the user does not own', function () {
    $user = makeUser();
    $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);
    $device = makeDevice($other);

    expect(app(RevokeUserDevice::class)->execute($user, (string) $device->getKey()))->toBeFalse();
});

it('revokes every other device but keeps the current one', function () {
    $user = makeUser();
    $current = makeDevice($user, 'Current');
    $a = makeDevice($user, 'A');
    $b = makeDevice($user, 'B');

    app(RevokeOtherUserDevices::class)->execute($user, (string) $current->getKey());

    expect($current->fresh()->revoked_at)->toBeNull()
        ->and($a->fresh()->revoked_at)->not->toBeNull()
        ->and($b->fresh()->revoked_at)->not->toBeNull();
});

it('lists only active devices, most recently seen first', function () {
    $user = makeUser();
    $old = makeDevice($user, 'Old');
    $old->forceFill(['last_seen_at' => now()->subDay()])->save();
    $recent = makeDevice($user, 'Recent');
    $recent->forceFill(['last_seen_at' => now()])->save();
    $revoked = makeDevice($user, 'Revoked');
    $revoked->forceFill(['revoked_at' => now()])->save();

    $devices = app(ListUserDevices::class)->execute($user);

    expect($devices)->toHaveCount(2)
        ->and($devices->first()->getKey())->toBe($recent->getKey());
});

it('renames an active device the user owns', function () {
    $user = makeUser();
    $device = makeDevice($user, 'Original');

    $updated = app(UpdateUserDeviceName::class)->execute($user, (string) $device->getKey(), '  Work Laptop  ');

    expect($updated)->not->toBeNull()
        ->and($updated->name)->toBe('Work Laptop');
});

it('does not rename a revoked device', function () {
    $user = makeUser();
    $device = makeDevice($user, 'Original');
    $device->forceFill(['revoked_at' => now()])->save();

    expect(app(UpdateUserDeviceName::class)->execute($user, (string) $device->getKey(), 'New'))->toBeNull();
});
