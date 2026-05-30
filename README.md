<div align="center">

# 📱 laravel-device-sessions

**Device-bound login sessions for Laravel — per-device "remember me" tokens, a "where am I signed in" list, and revoke/rename. Privacy-respecting and Fortify-agnostic.**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kirchdev/laravel-device-sessions.svg?style=flat-square&color=4f46e5)](https://packagist.org/packages/kirchdev/laravel-device-sessions)
[![Total Downloads](https://img.shields.io/packagist/dt/kirchdev/laravel-device-sessions.svg?style=flat-square&color=4f46e5)](https://packagist.org/packages/kirchdev/laravel-device-sessions)
[![Tests](https://img.shields.io/github/actions/workflow/status/kirchDev/laravel-device-sessions/ci.yml?branch=main&style=flat-square&label=tests)](https://github.com/kirchDev/laravel-device-sessions/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/kirchdev/laravel-device-sessions/php?style=flat-square&color=8993be)](https://packagist.org/packages/kirchdev/laravel-device-sessions)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/kirchdev/laravel-device-sessions/illuminate%2Fsupport?style=flat-square&label=laravel&color=ff2d20)](https://packagist.org/packages/kirchdev/laravel-device-sessions)
[![License: MIT](https://img.shields.io/packagist/l/kirchdev/laravel-device-sessions.svg?style=flat-square&color=10b981)](LICENSE)

</div>

---

```php
$user->devices; // every browser signed in, with masked IP, OS and last-seen — GitHub-style
```

That's it. Multiple concurrent device-bound remember-me tokens, a manageable device list, and revoke/rename — without touching your login controllers.

## ✨ Features

- **🔐 Device-bound remember-me** — a custom `device-aware-eloquent` auth driver binds each remember token to a device row + cookie instead of the single `remember_token` column. One active token per device, rotated on every login.
- **📋 "Where am I signed in"** — list active devices (OS, friendly name, masked IP, last-seen), then revoke one, revoke all others, or rename — all as plain actions.
- **🕵️ Privacy-respecting** — IP masking by default (IPv4 → /24, IPv6 hardened to /48). Swap it for your own via the `IpMasker` contract.
- **🔌 Fortify-agnostic** — works under any login mechanism (`Auth::attempt`, Breeze, Jetstream, Fortify). The two-factor cookie bridge auto-wires **only** when Fortify is installed.
- **🧩 Overridable everything** — name parsing, OS detection, cookie policy, IP masking and token hashing are all contracts bound to sensible defaults.
- **🧰 Config-driven schema** — model / table / key types (`id` / `uuid` / `ulid`) all overridable. UUID setups supported out of the box.
- **📡 Event-driven** — `DeviceTouched` lets you react (e.g. write your own `user.last_seen_at`) without the package assuming your schema.
- **🧪 Library-grade** — Pest 4 + Testbench, no host app needed.

## 📦 Installation

```bash
composer require kirchdev/laravel-device-sessions
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=device-sessions-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=device-sessions-config
```

> [!IMPORTANT]
> Set `device-sessions.keys.*` and `table_names.*` **before** migrating — the migrations read config at run time. `keys.user_key_type` must match your users-table primary key type.

## 🚀 Quick start

Add the `HasDeviceSessions` trait to your authenticatable model:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use KirchDev\DeviceSessions\Concerns\HasDeviceSessions;

class User extends Authenticatable
{
    use HasDeviceSessions;
}
```

Opt the auth provider into the device-aware driver:

```php
// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'device-aware-eloquent', // was 'eloquent'
        'model' => App\Models\User::class,
    ],
],
```

Track the device per request by aliasing and attaching the middleware:

```php
// bootstrap/app.php
use KirchDev\DeviceSessions\Http\Middleware\TrackAuthenticatedUserDevice;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['track.device' => TrackAuthenticatedUserDevice::class]);
})
```

```php
Route::middleware(['auth', 'track.device'])->group(function () {
    // ...authenticated routes
});
```

That's the whole wiring. From here, remember-me logins are device-bound and the device list populates automatically.

## 📋 Managing devices

Every device operation is a plain action you call from your own controllers — the
package ships **no** routes, so the response shape stays yours:

```php
use KirchDev\DeviceSessions\Actions\{
    ListUserDevices,
    RevokeUserDevice,
    RevokeOtherUserDevices,
    UpdateUserDeviceName,
};

$devices = app(ListUserDevices::class)->execute($user);          // active devices, last-seen first
app(RevokeUserDevice::class)->execute($user, $deviceId);         // revoke one (+ its tokens)
app(RevokeOtherUserDevices::class)->execute($user, $currentId);  // keep only the current device
app(UpdateUserDeviceName::class)->execute($user, $deviceId, 'Work Laptop');
```

The middleware exposes the current device as a request attribute so you can mark
"this device" in the list:

```php
$currentDeviceId = $request->attributes->get('current_device_id');
```

## 🧩 Overridable contracts

Every host-facing behaviour is a contract bound to a default — rebind any of them
in a service provider:

| Contract              | Default                      | Controls                               |
| :-------------------- | :--------------------------- | :------------------------------------- |
| `DeviceResolver`      | `ResolveOrCreateUserDevice…` | cookie → bootstrap-cache → create flow |
| `DeviceNameResolver`  | `DefaultDeviceNameResolver`  | User-Agent → "Chrome on Windows"       |
| `OsFamilyDetector`    | `DefaultOsFamilyDetector`    | User-Agent → `DeviceOsFamily`          |
| `DeviceCookieFactory` | `DeviceCookieBuilder`        | device cookie name / TTL / SameSite    |
| `IpMasker`            | `DefaultIpMasker`            | IP minimisation (IPv4 /24, IPv6 /48)   |
| `RememberTokenHasher` | `Sha256RememberTokenHasher`  | at-rest token hashing                  |

```php
$this->app->bind(
    \KirchDev\DeviceSessions\Contracts\IpMasker::class,
    \App\Support\MyStrictIpMasker::class,
);
```

## 📡 Events & Fortify

`TouchDeviceLastSeen` fires `DeviceTouched` on a real (throttled) touch — listen to
it instead of patching the package when you want to, for example, stamp your own
user column:

```php
use KirchDev\DeviceSessions\Events\DeviceTouched;

Event::listen(function (DeviceTouched $event) {
    $event->user->forceFill(['last_seen_at' => now()])->save();
});
```

Two opt-in integrations:

- **`OtherDeviceLogout`** → revokes all other devices (mirrors `Auth::logoutOtherDevices()`). Toggle via `device-sessions.events`.
- **Fortify two-factor** → when Fortify is installed, a listener on `TwoFactorAuthenticationChallenged` queues the device cookie at the challenge point (the normal `Login` event hasn't fired yet). Auto-wired via `class_exists` — the package never `require`s Fortify. Using a different 2FA flow? Write your own bridge against the `DeviceResolver` contract.

## 🧹 Pruning revoked devices

Revoked devices are kept (for an audit/undo window), then pruned:

```bash
php artisan device-sessions:prune            # uses device-sessions.prune.retention_days (default 180)
php artisan device-sessions:prune --days=90
```

The command ships **unscheduled** — wire it into your scheduler:

```php
Schedule::command('device-sessions:prune')->dailyAt('03:10');
```

## ⚙️ Configuration highlights

`config/device-sessions.php` is parameterised — see the file for inline docs. Most common knobs:

| Key                    | What it controls                                                                 |
| :--------------------- | :------------------------------------------------------------------------------- |
| `models.*`             | Swap the `user` / `device` / `remember_token` Eloquent models.                   |
| `table_names.*`        | Override defaults if they collide with existing tables.                          |
| `keys.*`               | `id` / `uuid` / `ulid` for device PKs and the user FK. Set **before** migrating. |
| `cookie.*`             | Device cookie name (default `device`), lifetime, SameSite, secure.               |
| `cache.*`              | Cache store, key prefix, login→2FA bootstrap TTL, last-seen throttle.            |
| `events.*`             | Toggle the core event listeners.                                                 |
| `prune.retention_days` | Retention window for the prune command (default 180).                            |

## 🧪 Testing

```bash
composer install
composer test       # Pest 4
composer pint       # Laravel Pint (test mode)
composer larastan   # Larastan / PHPStan
```

The test suite runs via Testbench + in-memory SQLite — no host app required.

## 🤝 Contributing

PRs welcome. Conventional Commits required (enforced via commitlint). Husky runs Pint + Larastan + oxlint + oxfmt on `git commit`, so you can mostly forget about style.

> [!TIP]
> Run `pnpm check:fix` (Node tooling) and `composer pint:fix` (PHP) before pushing — CI will catch what husky missed.

## 🛣️ Versioning

[Semantic Versioning](https://semver.org/). Release notes in [CHANGELOG.md](CHANGELOG.md) — managed by [release-please](https://github.com/googleapis/release-please).

## 📄 License

[MIT](LICENSE) © [Titus Kirch](https://github.com/TitusKirch/) / [IT-Dienstleistungen Titus Kirch](https://kirch.dev)
