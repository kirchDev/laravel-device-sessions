<?php

declare(strict_types=1);

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Models\UserDevice;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

it('revokes other devices when OtherDeviceLogout fires', function () {
    $user = makeUser();
    $current = makeDevice($user, 'Current');
    $other = makeDevice($user, 'Other');

    $request = requestWith();
    $request->attributes->set('current_device_id', (string) $current->getKey());
    app()->instance('request', $request);

    event(new OtherDeviceLogout('web', $user));

    expect($current->fresh()->revoked_at)->toBeNull()
        ->and($other->fresh()->revoked_at)->not->toBeNull();
});

it('queues the device cookie on the Fortify two-factor challenge', function () {
    $user = makeUser();
    app()->instance('request', requestWith());

    event(new TwoFactorAuthenticationChallenged($user));

    expect(UserDevice::query()->where('user_id', $user->getKey())->count())->toBe(1)
        ->and(Cookie::getQueuedCookies())->not->toBeEmpty();
});
