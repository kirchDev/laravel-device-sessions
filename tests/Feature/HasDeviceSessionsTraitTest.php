<?php

declare(strict_types=1);

it('exposes the user devices relation', function () {
    $user = makeUser();
    $a = makeDevice($user, 'A');
    $b = makeDevice($user, 'B');

    expect($user->devices()->count())->toBe(2)
        ->and($user->devices->pluck('id')->all())->toContain($a->getKey(), $b->getKey());
});

it('resolves the current device from the request attribute', function () {
    $user = makeUser();
    $device = makeDevice($user);

    $request = requestWith();
    $request->attributes->set('current_device_id', (string) $device->getKey());
    app()->instance('request', $request);

    expect($user->currentDeviceId())->toBe((string) $device->getKey())
        ->and($user->currentDevice()?->getKey())->toBe($device->getKey());
});

it('falls back to the device cookie for the current device id', function () {
    $user = makeUser();
    $device = makeDevice($user);

    $request = requestWith();
    $request->cookies->set('device', (string) $device->getKey());
    app()->instance('request', $request);

    expect($user->currentDeviceId())->toBe((string) $device->getKey());
});

it('returns null when no current device can be identified', function () {
    $user = makeUser();
    app()->instance('request', requestWith());

    expect($user->currentDeviceId())->toBeNull()
        ->and($user->currentDevice())->toBeNull();
});

it('loads the owning user from a device', function () {
    $user = makeUser();
    $device = makeDevice($user);

    expect($device->user)->not->toBeNull()
        ->and($device->user->getAuthIdentifier())->toBe($user->getAuthIdentifier());
});
