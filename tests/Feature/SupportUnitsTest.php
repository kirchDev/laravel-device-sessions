<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Enums\DeviceOsFamily;
use KirchDev\DeviceSessions\Support\DefaultDeviceNameResolver;
use KirchDev\DeviceSessions\Support\DefaultIpMasker;

it('masks IPv4 to a /24', function () {
    expect(new DefaultIpMasker()->mask('203.0.113.55'))->toBe('203.0.113.0');
});

it('masks IPv6 to a hardened /48', function () {
    expect(new DefaultIpMasker()->mask('2001:db8:1234:5678:9abc:def0:1234:5678'))->toBe('2001:db8:1234::');
});

it('returns null for empty or invalid ip input', function () {
    $masker = new DefaultIpMasker;

    expect($masker->mask(null))->toBeNull()
        ->and($masker->mask(''))->toBeNull()
        ->and($masker->mask('not-an-ip'))->toBeNull();
});

it('builds friendly device names from the user agent', function () {
    $resolver = new DefaultDeviceNameResolver;

    expect($resolver->resolve('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'))->toBe('Chrome on Windows')
        ->and($resolver->resolve('Mozilla/5.0 (Macintosh) Safari/605'))->toBe('Safari on macOS')
        ->and($resolver->resolve(null))->toBe('Unknown device');
});

it('classifies the os family from the user agent', function () {
    expect(DeviceOsFamily::fromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'))->toBe(DeviceOsFamily::IOS)
        ->and(DeviceOsFamily::fromUserAgent('Mozilla/5.0 (X11; Linux x86_64)'))->toBe(DeviceOsFamily::Linux)
        ->and(DeviceOsFamily::fromUserAgent('weird-bot'))->toBe(DeviceOsFamily::Unknown);
});
