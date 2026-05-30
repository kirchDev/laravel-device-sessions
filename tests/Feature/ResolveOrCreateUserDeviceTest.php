<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Enums\DeviceOsFamily;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Tests\Fixtures\User;

it('creates a new device with parsed name, os family and masked ip', function () {
    $user = makeUser();

    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    expect($device)->toBeInstanceOf(UserDevice::class)
        ->and($device->exists)->toBeTrue()
        ->and($device->name)->toBe('Chrome on Windows')
        ->and($device->os_family)->toBe(DeviceOsFamily::Windows)
        ->and($device->ip_address)->toBe('203.0.113.0')
        ->and($device->getAttribute('user_id'))->toBe($user->getKey());

    expect(UserDevice::count())->toBe(1);
});

it('reuses the device referenced by the device cookie', function () {
    $user = makeUser();
    $existing = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    $request = requestWith();
    $request->cookies->set('device', (string) $existing->getKey());

    $again = app(DeviceResolver::class)->resolveOrCreate($user, $request);

    expect($again->getKey())->toBe($existing->getKey());
    expect(UserDevice::count())->toBe(1);
});

it('bridges the login to two-factor gap via the session bootstrap cache', function () {
    $user = makeUser();

    $session = app('session')->driver();

    $first = requestWith();
    $first->setLaravelSession($session);
    $created = app(DeviceResolver::class)->resolveOrCreate($user, $first);

    // No device cookie on the follow-up request, but the same session: the
    // bootstrap cache should resolve the same device instead of creating a new one.
    $second = requestWith();
    $second->setLaravelSession($session);
    $resolved = app(DeviceResolver::class)->resolveOrCreate($user, $second);

    expect($resolved->getKey())->toBe($created->getKey());
    expect(UserDevice::count())->toBe(1);
});

it('does not reuse another users device even with a matching cookie', function () {
    $owner = makeUser();
    $other = User::create(['name' => 'Other', 'email' => 'other@example.com']);

    $device = app(DeviceResolver::class)->resolveOrCreate($owner, requestWith());

    $request = requestWith();
    $request->cookies->set('device', (string) $device->getKey());

    $resolved = app(DeviceResolver::class)->resolveOrCreate($other, $request);

    expect($resolved->getKey())->not->toBe($device->getKey());
    expect(UserDevice::count())->toBe(2);
});
