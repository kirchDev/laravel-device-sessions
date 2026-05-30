<?php

declare(strict_types=1);

use Illuminate\Auth\Events\OtherDeviceLogout;
use KirchDev\DeviceSessions\Models\UserDevice;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

it('ignores the two-factor challenge for a non-authenticatable user', function () {
    app()->instance('request', requestWith());

    event(new TwoFactorAuthenticationChallenged(new stdClass));

    expect(UserDevice::count())->toBe(0);
});

it('revokes other devices using the device cookie when no attribute is set', function () {
    $user = makeUser();
    $current = makeDevice($user, 'Current');
    $other = makeDevice($user, 'Other');

    $request = requestWith();
    $request->cookies->set('device', (string) $current->getKey());
    app()->instance('request', $request);

    event(new OtherDeviceLogout('web', $user));

    expect($current->fresh()->revoked_at)->toBeNull()
        ->and($other->fresh()->revoked_at)->not->toBeNull();
});

it('does nothing on OtherDeviceLogout when no current device is identifiable', function () {
    $user = makeUser();
    $device = makeDevice($user, 'A');

    app()->instance('request', requestWith());

    event(new OtherDeviceLogout('web', $user));

    expect($device->fresh()->revoked_at)->toBeNull();
});
