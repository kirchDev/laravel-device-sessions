<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Http\Middleware\TrackAuthenticatedUserDevice;
use KirchDev\DeviceSessions\Models\UserDevice;

it('resolves the device, sets current_device_id and queues the cookie', function () {
    $user = makeUser();
    $request = requestWith();
    $request->setUserResolver(fn () => $user);

    $response = app(TrackAuthenticatedUserDevice::class)->handle($request, fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(UserDevice::count())->toBe(1);

    $deviceId = $request->attributes->get('current_device_id');

    expect($deviceId)->toBe((string) UserDevice::query()->sole()->getKey())
        ->and(Cookie::getQueuedCookies())->not->toBeEmpty();
});

it('is a no-op for guests', function () {
    $request = requestWith();
    $request->setUserResolver(fn () => null);

    app(TrackAuthenticatedUserDevice::class)->handle($request, fn () => new Response('ok'));

    expect(UserDevice::count())->toBe(0)
        ->and($request->attributes->get('current_device_id'))->toBeNull();
});

it('does not re-queue the cookie when it already matches', function () {
    $user = makeUser();

    $first = requestWith();
    $first->setUserResolver(fn () => $user);
    app(TrackAuthenticatedUserDevice::class)->handle($first, fn () => new Response('ok'));

    $deviceId = (string) UserDevice::query()->sole()->getKey();

    Cookie::flushQueuedCookies();

    $second = requestWith();
    $second->setUserResolver(fn () => $user);
    $second->cookies->set('device', $deviceId);
    app(TrackAuthenticatedUserDevice::class)->handle($second, fn () => new Response('ok'));

    expect(Cookie::getQueuedCookies())->toBeEmpty();
});
