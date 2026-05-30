<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Tests\Fixtures\UuidUser;

it('generates uuid keys and resolves a device on uuid setups', function () {
    $user = UuidUser::create(['name' => 'U', 'email' => 'u@example.com']);

    expect(Str::isUuid($user->getKey()))->toBeTrue();

    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    expect($device)->toBeInstanceOf(UserDevice::class)
        ->and(Str::isUuid($device->getKey()))->toBeTrue()
        ->and($device->getAttribute('user_id'))->toBe($user->getKey());
});

it('binds a device-bound remember token on uuid setups', function () {
    $user = UuidUser::create(['name' => 'U', 'email' => 'u2@example.com']);
    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    $device->rememberTokens()->create(['token_hash' => hash('sha256', 'tok')]);

    expect(Str::isUuid($device->rememberTokens()->sole()->getKey()))->toBeTrue();
});
