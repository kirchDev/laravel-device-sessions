---
name: device-sessions-development
description: 'Work with kirchdev/laravel-device-sessions: build the device list, revoke and rename devices, wire device-bound remember-me and Fortify, swap a contract, and test all of it.'
---

# Device sessions development

Use this when building a "where am I signed in" screen, wiring device-bound remember-me, adapting a
2FA flow, replacing one of the package's default behaviours, or debugging why a device row did not
appear in an application that uses `kirchdev/laravel-device-sessions`.

## Listing, revoking and renaming

The package ships **no routes and no controllers** — the response shape stays the application's.
Every operation is a plain action resolved from the container:

```php
use KirchDev\DeviceSessions\Actions\{
    ListUserDevices, RevokeUserDevice, RevokeOtherUserDevices, UpdateUserDeviceName
};

$devices = app(ListUserDevices::class)->execute($user);              // active devices, last-seen first
app(RevokeUserDevice::class)->execute($user, $deviceId);             // bool — revokes the device + its tokens
app(RevokeOtherUserDevices::class)->execute($user, $currentDeviceId); // keep only the current device
app(UpdateUserDeviceName::class)->execute($user, $deviceId, 'Work Laptop');
```

- Mark "this device" from `$user->currentDevice()` / `$user->currentDeviceId()`, or from the
  `current_device_id` request attribute the tracking middleware sets. Do not re-derive it from the
  cookie.
- Every action takes the `$user` as its first argument and scopes to that user's devices, so a
  device id coming from a request cannot reach another user's row.
- Devices carry two timestamps on purpose: `last_seen_at` is any activity (throttled by
  `device-sessions.cache.last_seen_throttle`), `last_used_at` is activity plus remember-token use.
- Revoked rows are kept for an audit/undo window and removed by `device-sessions:prune`
  (`device-sessions.prune.retention_days`, default 180). The command ships **unscheduled**:

```php
Schedule::command('device-sessions:prune')->dailyAt('03:10');
```

## Device-bound remember-me (the part that surprises people)

Standard Laravel keeps one `remember_token` column per user, so a "sign out everywhere" is
all-or-nothing. This package replaces the auth provider:

```php
// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'device-aware-eloquent', // was 'eloquent'
        'model' => App\Models\User::class,
    ],
],
```

- One active token per device, hashed at rest and rotated on login. `device-sessions.remember.lifetime`
  is the expiry in minutes (`null` = never).
- If the driver is left as `eloquent`, the device list still fills but nothing is device-bound —
  that is the usual cause of "revoking a device does not log it out".
- The device itself comes from the tracking middleware
  (`KirchDev\DeviceSessions\Http\Middleware\TrackAuthenticatedUserDevice`), which must be aliased and
  attached to the authenticated routes. A route group without it produces no device rows.

## Events and Fortify

- `KirchDev\DeviceSessions\Events\DeviceTouched` fires on a real (throttled) touch. Listen to it
  instead of patching the package — for instance to stamp an application-owned column:

```php
Event::listen(fn (DeviceTouched $event) => $event->user->forceFill(['last_seen_at' => now()])->save());
```

- `device-sessions.events.revoke_other_devices_on_other_device_logout` mirrors
  `Auth::logoutOtherDevices()` onto the device rows; `events.enabled` turns the listeners off wholesale.
- Fortify is **never required**. A bridge listener queues the device cookie at the two-factor
  challenge — where the `Login` event has not fired yet — and is registered only when Fortify is
  installed. A different 2FA flow needs its own bridge, written against the `DeviceResolver` contract
  rather than against the Fortify listener.

## Swapping a contract

Every host-facing behaviour is a contract in `KirchDev\DeviceSessions\Contracts`, bound to a
`Default*` implementation. Rebind it in a service provider; do not extend or patch the shipped class.

| Contract              | Controls                                            |
| :-------------------- | :-------------------------------------------------- |
| `DeviceResolver`      | cookie → bootstrap-cache → create flow              |
| `DeviceNameResolver`  | User-Agent → a friendly name                        |
| `OsFamilyDetector`    | User-Agent → `DeviceOsFamily`                       |
| `DeviceCookieFactory` | device cookie name, TTL, SameSite                   |
| `IpMasker`            | IP minimisation (IPv4 `/24`, IPv6 `/48` by default) |
| `RememberTokenHasher` | at-rest token hashing                               |

```php
$this->app->bind(
    \KirchDev\DeviceSessions\Contracts\IpMasker::class,
    \App\Support\MyStrictIpMasker::class,
);
```

The models are swappable the same way, through `config('device-sessions.models.device')` and
`...remember_token` — resolve them from config rather than referencing the package classes.

## Testing

- Drive the middleware, not the actions alone: a device row is created by
  `TrackAuthenticatedUserDevice` on an authenticated request, so a test that only calls an action
  proves nothing about the wiring.
- Give the test user the `HasDeviceSessions` trait and set the users provider driver to
  `device-aware-eloquent`, or the remember-me path under test is not the one production runs.
- Assert revocation by its effect: the device gone from `ListUserDevices`, and a remember token
  from it no longer authenticating. Nothing is deleted — `RevokeUserDevice` stamps `revoked_at` on
  the device and its tokens, and the rows only leave the tables when `device-sessions:prune` passes
  the retention window. An `assertDatabaseMissing` on the token table will fail.
- `last_seen_at` is throttled, so a second request inside the throttle window deliberately does not
  move it. Travel the clock instead of asserting on two consecutive requests.
- `keys.user_key_type` must match the users table in the test schema too; a mismatch shows up as a
  foreign-key failure at migrate time, not at assert time.
