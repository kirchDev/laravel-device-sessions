<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use KirchDev\DeviceSessions\Actions\TouchDeviceLastSeen;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Events\DeviceTouched;

it('writes both device timestamps and dispatches DeviceTouched', function () {
    $user = makeUser();
    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    Event::fake([DeviceTouched::class]);

    app(TouchDeviceLastSeen::class)->execute($user, $device);

    $device->refresh();

    expect($device->last_seen_at)->not->toBeNull()
        ->and($device->last_used_at)->not->toBeNull();

    Event::assertDispatched(DeviceTouched::class, function (DeviceTouched $event) use ($user, $device) {
        return $event->user->getAuthIdentifier() === $user->getAuthIdentifier()
            && $event->device->getKey() === $device->getKey();
    });
});

it('throttles repeated touches within the window', function () {
    $user = makeUser();
    $device = app(DeviceResolver::class)->resolveOrCreate($user, requestWith());

    Event::fake([DeviceTouched::class]);

    app(TouchDeviceLastSeen::class)->execute($user, $device);
    app(TouchDeviceLastSeen::class)->execute($user, $device);

    Event::assertDispatchedTimes(DeviceTouched::class, 1);
});
