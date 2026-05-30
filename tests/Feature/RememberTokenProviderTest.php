<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Auth\DeviceAwareEloquentUserProvider;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Models\UserDeviceRememberToken;

function deviceProvider(): DeviceAwareEloquentUserProvider
{
    $provider = Auth::createUserProvider('users');

    expect($provider)->toBeInstanceOf(DeviceAwareEloquentUserProvider::class);

    /** @var DeviceAwareEloquentUserProvider $provider */
    return $provider;
}

it('binds a remember token to a device and queues the device cookie', function () {
    $user = makeUser();
    app()->instance('request', requestWith());

    deviceProvider()->updateRememberToken($user, 'raw-token');

    $device = UserDevice::query()->where('user_id', $user->getKey())->sole();

    expect($device->rememberTokens()->whereNull('revoked_at')->count())->toBe(1);
    expect(UserDeviceRememberToken::query()->sole()->token_hash)->toBe(hash('sha256', 'raw-token'));
    expect(Cookie::getQueuedCookies())->not->toBeEmpty();
});

it('rotates the active token on each update, keeping a single live token', function () {
    $user = makeUser();
    $request = requestWith();
    app()->instance('request', $request);

    $provider = deviceProvider();
    $provider->updateRememberToken($user, 'first');

    $device = UserDevice::query()->sole();

    // The browser now carries the device cookie on the next login.
    $request->cookies->set('device', (string) $device->getKey());
    $provider->updateRememberToken($user, 'second');

    expect($device->rememberTokens()->count())->toBe(2)
        ->and($device->rememberTokens()->whereNull('revoked_at')->count())->toBe(1)
        ->and($device->rememberTokens()->whereNull('revoked_at')->sole()->token_hash)
        ->toBe(hash('sha256', 'second'));
});

it('retrieves the user by a valid device-bound token and cookie', function () {
    $user = makeUser();
    app()->instance('request', requestWith());
    deviceProvider()->updateRememberToken($user, 'raw-token');

    $device = UserDevice::query()->sole();

    $request = requestWith();
    $request->cookies->set('device', (string) $device->getKey());
    app()->instance('request', $request);

    $resolved = deviceProvider()->retrieveByToken($user->getKey(), 'raw-token');

    expect($resolved)->not->toBeNull()
        ->and($resolved->getAuthIdentifier())->toBe($user->getKey());
});

it('rejects a token when the device cookie is missing', function () {
    $user = makeUser();
    app()->instance('request', requestWith());
    deviceProvider()->updateRememberToken($user, 'raw-token');

    // New request without the device cookie.
    app()->instance('request', requestWith());

    expect(deviceProvider()->retrieveByToken($user->getKey(), 'raw-token'))->toBeNull();
});

it('rejects a revoked token', function () {
    $user = makeUser();
    app()->instance('request', requestWith());
    deviceProvider()->updateRememberToken($user, 'raw-token');

    $device = UserDevice::query()->sole();
    $device->rememberTokens()->update(['revoked_at' => now()]);

    $request = requestWith();
    $request->cookies->set('device', (string) $device->getKey());
    app()->instance('request', $request);

    expect(deviceProvider()->retrieveByToken($user->getKey(), 'raw-token'))->toBeNull();
});
