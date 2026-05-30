<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Tests\Fixtures\UlidUser;

it('generates ulid keys and resolves a device on ulid setups', function () {
    $user = UlidUser::create(['name' => 'U', 'email' => 'ulid@example.com']);

    expect(Str::isUlid($user->getKey()))->toBeTrue();

    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    expect($device)->toBeInstanceOf(UserDevice::class)
        ->and(Str::isUlid($device->getKey()))->toBeTrue()
        ->and($device->getAttribute('user_id'))->toBe($user->getKey());

    $device->rememberTokens()->create(['token_hash' => hash('sha256', 'tok')]);

    expect(Str::isUlid($device->rememberTokens()->sole()->getKey()))->toBeTrue();
});
