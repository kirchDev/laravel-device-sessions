<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Tests\Fixtures\User;
use KirchDev\DeviceSessions\Tests\TestCase;
use KirchDev\DeviceSessions\Tests\UuidKeysTestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(UuidKeysTestCase::class)->in('UuidKeys');

function makeUser(string $email = 'test@example.com'): User
{
    return User::create(['name' => 'Test', 'email' => $email]);
}

function requestWith(
    string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64) Chrome/120.0',
    string $ip = '203.0.113.55',
): Request {
    $request = Request::create('/');
    $request->headers->set('User-Agent', $userAgent);
    $request->server->set('REMOTE_ADDR', $ip);

    return $request;
}

function makeDevice(User $user, string $name = 'Device'): UserDevice
{
    /** @var UserDevice $device */
    $device = UserDevice::query()->create([
        'user_id' => $user->getKey(),
        'type' => 'web',
        'os_family' => 'unknown',
        'name' => $name,
    ]);

    $device->rememberTokens()->create(['token_hash' => hash('sha256', $name)]);

    return $device;
}
