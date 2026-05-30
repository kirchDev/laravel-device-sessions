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

That's it. Concurrent device-bound remember-me tokens, a "where am I signed in" list, and revoke/rename — without touching your login controllers.

## 📦 Install & run

```bash
composer require kirchdev/laravel-device-sessions
php artisan vendor:publish --tag=device-sessions-migrations
php artisan migrate
```

> [!IMPORTANT]
> Publish the config first (`--tag=device-sessions-config`) and set `device-sessions.keys.*` + `table_names.*` **before** migrating — the migrations read config at run time, and `keys.user_key_type` must match your users-table primary key.

Add the `HasDeviceSessions` trait to your authenticatable model and point its auth provider at the device-aware driver:

```php
use KirchDev\DeviceSessions\Concerns\HasDeviceSessions;

class User extends Authenticatable
{
    use HasDeviceSessions;
}
```

```php
// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'device-aware-eloquent', // was 'eloquent'
        'model' => App\Models\User::class,
    ],
],
```

Then alias the tracking middleware and attach it to your authenticated routes — that's the whole wiring; remember-me logins are now device-bound and the device list populates automatically:

```php
// bootstrap/app.php
use KirchDev\DeviceSessions\Http\Middleware\TrackAuthenticatedUserDevice;

->withMiddleware(fn (Middleware $middleware) => $middleware->alias([
    'track.device' => TrackAuthenticatedUserDevice::class,
]))
```

```php
Route::middleware(['auth', 'track.device'])->group(function () {
    // ...authenticated routes
});
```

## ✨ Features

- **🔐 Device-bound remember-me** — a custom `device-aware-eloquent` driver binds each remember token to a device row + cookie instead of the single `remember_token` column (one active token per device, rotated on login).
- **📋 "Where am I signed in"** — list active devices (OS, friendly name, masked IP, last-seen), revoke one, revoke all others, or rename — all as plain actions.
- **🕵️ Privacy-respecting** — IP masking on by default (IPv4 → /24, IPv6 → /48), swappable via the `IpMasker` contract.
- **🔌 Fortify-agnostic** — works under any login mechanism; the two-factor cookie bridge auto-wires only when Fortify is present.
- **🧩 Overridable everything** — name parsing, OS detection, cookie policy, IP masking and token hashing are contracts with sensible defaults.
- **🧰 Config-driven schema** — models, table names and key types (`id` / `uuid` / `ulid`) all overridable.
- **📡 Event-driven** — a `DeviceTouched` event lets you react without the package assuming your schema.
- **🧪 Library-grade** — Pest 4 + Testbench, no host app needed.

## 📋 Managing devices

The package ships **no routes** — every operation is a plain action you call from your own controllers, so the response shape stays yours:

```php
use KirchDev\DeviceSessions\Actions\{ListUserDevices, RevokeUserDevice, RevokeOtherUserDevices, UpdateUserDeviceName};

$devices = app(ListUserDevices::class)->execute($user);          // active devices, last-seen first
app(RevokeUserDevice::class)->execute($user, $deviceId);         // revoke one (+ its tokens)
app(RevokeOtherUserDevices::class)->execute($user, $currentId);  // keep only the current device
app(UpdateUserDeviceName::class)->execute($user, $deviceId, 'Work Laptop');
```

The middleware exposes the active device as the `current_device_id` request attribute (also `$user->currentDevice()`), so you can flag "this device" in the list.

## 🧩 Overridable contracts

Every host-facing behaviour is a contract bound to a `Default*` — rebind any of them in a service provider:

```php
$this->app->bind(
    \KirchDev\DeviceSessions\Contracts\IpMasker::class,
    \App\Support\MyStrictIpMasker::class,
);
```

<details>
<summary>All contracts and their defaults</summary>

| Contract              | Default                      | Controls                               |
| :-------------------- | :--------------------------- | :------------------------------------- |
| `DeviceResolver`      | `ResolveOrCreateUserDevice…` | cookie → bootstrap-cache → create flow |
| `DeviceNameResolver`  | `DefaultDeviceNameResolver`  | User-Agent → "Chrome on Windows"       |
| `OsFamilyDetector`    | `DefaultOsFamilyDetector`    | User-Agent → `DeviceOsFamily`          |
| `DeviceCookieFactory` | `DeviceCookieBuilder`        | device cookie name / TTL / SameSite    |
| `IpMasker`            | `DefaultIpMasker`            | IP minimisation (IPv4 /24, IPv6 /48)   |
| `RememberTokenHasher` | `Sha256RememberTokenHasher`  | at-rest token hashing                  |

</details>

## 📡 Events & Fortify

`TouchDeviceLastSeen` fires `DeviceTouched` on a real (throttled) touch — listen instead of patching the package, e.g. to stamp your own user column:

```php
Event::listen(fn (DeviceTouched $event) => $event->user->forceFill(['last_seen_at' => now()])->save());
```

Two opt-in integrations:

- **`OtherDeviceLogout`** → revokes all other devices (mirrors `Auth::logoutOtherDevices()`); toggle via `device-sessions.events`.
- **Fortify two-factor** → a listener queues the device cookie at the 2FA challenge (where the `Login` event hasn't fired yet), auto-wired via `class_exists` so Fortify is never required. Using another 2FA flow? Write your own bridge against the `DeviceResolver` contract.

## 🧹 Pruning revoked devices

Revoked devices are kept (audit/undo window), then pruned. The command ships **unscheduled** — wire it into your scheduler with `Schedule::command('device-sessions:prune')->dailyAt('03:10')`:

```bash
php artisan device-sessions:prune            # retention from device-sessions.prune.retention_days (180)
php artisan device-sessions:prune --days=90
```

## ⚙️ Configuration

`config/device-sessions.php` is parameterised with inline docs — e.g. rename the cookie or switch key types:

```php
'cookie' => ['name' => 'device', 'same_site' => 'lax'],
'keys'   => ['primary_key_type' => 'id', 'user_key_type' => 'id'],
```

<details>
<summary>All configuration keys</summary>

| Key                    | What it controls                                                                 |
| :--------------------- | :------------------------------------------------------------------------------- |
| `models.*`             | Swap the `user` / `device` / `remember_token` Eloquent models.                   |
| `table_names.*`        | Override defaults if they collide with existing tables.                          |
| `keys.*`               | `id` / `uuid` / `ulid` for device PKs and the user FK. Set **before** migrating. |
| `cookie.*`             | Device cookie name (default `device`), lifetime, SameSite, secure.               |
| `cache.*`              | Cache store, key prefix, login→2FA bootstrap TTL, last-seen throttle.            |
| `remember.lifetime`    | Minutes until a remember token expires (`null` = never).                         |
| `events.*`             | Toggle the core event listeners.                                                 |
| `prune.retention_days` | Retention window for the prune command (default 180).                            |

</details>

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
