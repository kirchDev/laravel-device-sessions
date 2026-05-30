<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Models\UserDevice;

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
