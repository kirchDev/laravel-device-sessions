<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Models\UserDeviceRememberToken;

it('logs a returning user back in from a device-bound remember cookie', function () {
    $user = makeUser();

    // --- Login with "remember me" within a request that has a session ---
    $loginRequest = Request::create('/login', 'POST');
    $loginRequest->setLaravelSession(app('session')->driver());
    app()->instance('request', $loginRequest);

    Auth::forgetGuards();
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');
    $guard->setRequest($loginRequest);
    $guard->login($user, true);

    $recallerName = $guard->getRecallerName();
    $queued = collect(Cookie::getQueuedCookies());
    $recaller = $queued->first(fn ($c) => $c->getName() === $recallerName);
    $deviceCookie = $queued->first(fn ($c) => $c->getName() === 'device');

    expect($recaller)->not->toBeNull('remember cookie should be queued')
        ->and($deviceCookie)->not->toBeNull('device cookie should be queued')
        ->and(UserDevice::query()->where('user_id', $user->getKey())->count())->toBe(1);

    // --- Fresh request: no session user, only the cookies the browser carries ---
    app('session')->driver()->flush();
    Auth::forgetGuards();

    $returning = Request::create('/');
    $returning->setLaravelSession(app('session')->driver());
    $returning->cookies->set($recallerName, $recaller->getValue());
    $returning->cookies->set('device', $deviceCookie->getValue());
    app()->instance('request', $returning);

    /** @var SessionGuard $guard2 */
    $guard2 = Auth::guard('web');
    $guard2->setRequest($returning);

    $resolved = $guard2->user();

    expect($resolved)->not->toBeNull('user should be resolved via the device-bound remember token')
        ->and($resolved->getAuthIdentifier())->toBe($user->getAuthIdentifier())
        ->and($guard2->viaRemember())->toBeTrue();
});

it('does not log a returning user in without the device cookie', function () {
    $user = makeUser();

    $loginRequest = Request::create('/login', 'POST');
    $loginRequest->setLaravelSession(app('session')->driver());
    app()->instance('request', $loginRequest);

    Auth::forgetGuards();
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');
    $guard->setRequest($loginRequest);
    $guard->login($user, true);

    $recaller = collect(Cookie::getQueuedCookies())
        ->first(fn ($c) => $c->getName() === $guard->getRecallerName());

    app('session')->driver()->flush();
    Auth::forgetGuards();

    // Recaller present, but the device cookie is missing → token must not bind.
    $returning = Request::create('/');
    $returning->setLaravelSession(app('session')->driver());
    $returning->cookies->set($guard->getRecallerName(), $recaller->getValue());
    app()->instance('request', $returning);

    $guard2 = Auth::guard('web');
    $guard2->setRequest($returning);

    expect($guard2->user())->toBeNull();
});

/**
 * Log the user in with "remember me" and return the cookies the response queues.
 *
 * @return array{recallerName: string, recaller: ?Symfony\Component\HttpFoundation\Cookie, device: ?Symfony\Component\HttpFoundation\Cookie}
 */
function rememberLogin(Authenticatable $user): array
{
    $loginRequest = Request::create('/login', 'POST');
    $loginRequest->setLaravelSession(app('session')->driver());
    app()->instance('request', $loginRequest);

    Auth::forgetGuards();
    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');
    $guard->setRequest($loginRequest);
    $guard->login($user, true);

    $queued = collect(Cookie::getQueuedCookies());

    return [
        'recallerName' => $guard->getRecallerName(),
        'recaller' => $queued->first(fn ($c) => $c->getName() === $guard->getRecallerName()),
        'device' => $queued->first(fn ($c) => $c->getName() === 'device'),
    ];
}

/**
 * A fresh request that carries only the given cookies, resolved through the guard.
 *
 * @param  array{recallerName: string, recaller: ?Symfony\Component\HttpFoundation\Cookie, device: ?Symfony\Component\HttpFoundation\Cookie}  $cookies
 */
function recallFrom(array $cookies): SessionGuard
{
    app('session')->driver()->flush();
    Auth::forgetGuards();
    Cookie::flushQueuedCookies();

    $returning = Request::create('/');
    $returning->setLaravelSession(app('session')->driver());
    $returning->cookies->set($cookies['recallerName'], $cookies['recaller']?->getValue());
    $returning->cookies->set('device', $cookies['device']?->getValue());
    app()->instance('request', $returning);

    /** @var SessionGuard $guard */
    $guard = Auth::guard('web');
    $guard->setRequest($returning);

    return $guard;
}

it('binds a device token for a user whose remember_token column predates the package', function () {
    $user = makeUser();
    DB::table('users')->where('id', $user->getKey())->update(['remember_token' => 'left-over-from-stock-laravel']);

    // Loaded the way Auth::attempt() loads it — through the configured provider.
    $loaded = Auth::guard('web')->getProvider()->retrieveByCredentials(['email' => $user->email]);
    $cookies = rememberLogin($loaded);

    expect($cookies['device'])->not->toBeNull('device cookie should be queued')
        ->and(UserDeviceRememberToken::query()->count())->toBe(1)
        ->and(DB::table('users')->where('id', $user->getKey())->value('remember_token'))->toBeNull();

    $guard = recallFrom($cookies);

    expect($guard->user()?->getAuthIdentifier())->toBe($user->getAuthIdentifier())
        ->and($guard->viaRemember())->toBeTrue();
});

it('keeps the plaintext remember token out of the users table when the user is saved after login', function () {
    $user = makeUser();

    rememberLogin($user);
    $user->forceFill(['name' => 'Renamed after login'])->save();

    expect(DB::table('users')->where('id', $user->getKey())->value('remember_token'))->toBeNull();
});

it('stamps the device as used when a remember token logs the user back in', function () {
    $user = makeUser();
    $cookies = rememberLogin($user);

    $device = UserDevice::query()->where('user_id', $user->getKey())->sole();
    $device->forceFill(['last_used_at' => now()->subDay()])->save();

    $this->travel(5)->minutes();
    recallFrom($cookies)->user();

    expect($device->refresh()->last_used_at->greaterThan(now()->subMinute()))->toBeTrue();
});
