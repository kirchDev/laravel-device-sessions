<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Models\UserDevice;

it('returns null for an empty remember token', function () {
    $user = makeUser();
    app()->instance('request', requestWith());

    expect(deviceProvider()->retrieveByToken($user->getKey(), ''))->toBeNull();
});

it('returns null when the user id is unknown', function () {
    $request = requestWith();
    $request->cookies->set('device', 'whatever');
    app()->instance('request', $request);

    expect(deviceProvider()->retrieveByToken(999999, 'tok'))->toBeNull();
});

it('is a no-op when updating with an empty token', function () {
    $user = makeUser();
    app()->instance('request', requestWith());

    deviceProvider()->updateRememberToken($user, '');

    expect(UserDevice::count())->toBe(0);
});

it('sets an expiry on the token when a lifetime is configured', function () {
    config()->set('device-sessions.remember.lifetime', 60);

    $user = makeUser();
    app()->instance('request', requestWith());

    deviceProvider()->updateRememberToken($user, 'raw');

    $token = UserDevice::query()->sole()->rememberTokens()->sole();

    expect($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->isFuture())->toBeTrue();
});
