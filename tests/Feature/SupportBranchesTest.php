<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Enums\DeviceOsFamily;
use KirchDev\DeviceSessions\Support\DefaultDeviceNameResolver;
use KirchDev\DeviceSessions\Support\DeviceCookieBuilder;

it('covers all browser and platform branches of the name resolver', function () {
    $resolver = new DefaultDeviceNameResolver;

    expect($resolver->resolve('Mozilla/5.0 Edg/120'))->toBe('Edge on Unknown OS')
        ->and($resolver->resolve('Mozilla/5.0 Firefox/120 Android'))->toBe('Firefox on Android')
        ->and($resolver->resolve('curl/8.0 (X11; Linux x86_64)'))->toBe('Browser on Linux');
});

it('classifies the remaining os families', function () {
    expect(DeviceOsFamily::fromUserAgent('Mozilla/5.0 (Linux; Android 14)'))->toBe(DeviceOsFamily::Android)
        ->and(DeviceOsFamily::fromUserAgent('Mozilla/5.0 (Windows NT 10.0)'))->toBe(DeviceOsFamily::Windows)
        ->and(DeviceOsFamily::fromUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X)'))->toBe(DeviceOsFamily::MacOS);
});

it('builds the device cookie from explicit config', function () {
    config()->set('device-sessions.cookie.secure', true);
    config()->set('device-sessions.cookie.same_site', 'strict');

    $cookie = (new DeviceCookieBuilder)->make(Request::create('/'), 'dev-1');

    expect($cookie->getName())->toBe('device')
        ->and($cookie->getValue())->toBe('dev-1')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('strict')
        ->and($cookie->isHttpOnly())->toBeTrue();
});

it('derives cookie secure from session.secure when not explicitly set', function () {
    config()->set('device-sessions.cookie.secure', null);
    config()->set('session.secure', true);

    $cookie = (new DeviceCookieBuilder)->make(Request::create('/'), 'dev-1');

    expect($cookie->isSecure())->toBeTrue();
});
