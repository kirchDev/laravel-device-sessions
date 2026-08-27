# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Agent instruction files

`CLAUDE.md` and `AGENTS.md` are kept **byte-identical**. `CLAUDE.md` is what Claude Code reads; `AGENTS.md` is what vendor-neutral agent tools read — Codex, OpenCode, Cursor, Copilot, and whatever follows them. Two real files, deliberately not a symlink: not every tool resolves one.

**After editing either file, copy it over the other — don't repeat the edit by hand:**

```bash
cp CLAUDE.md AGENTS.md   # or the reverse, whichever you just edited
```

Retyping a change is exactly how the two drift; one reflowed line or reworded clause is enough. `diff CLAUDE.md AGENTS.md` must print nothing. If it ever does, treat it as a defect and fix it by letting one file win wholesale — never by merging them.

## What this is

`kirchdev/laravel-device-sessions` is a standalone Composer **library** (not an application) that adds device-bound login sessions to Laravel 13: per-device "remember me" tokens, a "where am I signed in" device list, and revoke/rename. It is privacy-respecting (IP masking) and Fortify-agnostic. PHP 8.4+, ships its own service provider auto-discovered via `extra.laravel.providers`.

The library has no host app — tests run against `orchestra/testbench` with in-memory SQLite.

## Commands

PHP (Composer scripts):

- `composer test` — Pest 4 suite via Testbench.
- `composer test -- --filter=SomeTest` — run a single test / pattern.
- `composer pint` — Laravel Pint in **test** mode (no writes). `composer pint:fix` to auto-fix.
- `composer larastan` — Larastan/PHPStan at `--memory-limit=512M`.

Node tooling (lint/format only, no app code):

- `pnpm check` / `pnpm check:fix` — oxlint + oxfmt over JS / JSON / YAML / MD.
- Husky runs Pint + Larastan + oxlint + oxfmt on commit via lint-staged. Don't `--no-verify` unless explicitly asked.

Commits **must** follow Conventional Commits (commitlint enforced). Releases are automated by release-please on `main`.

## Architecture

The core is a custom auth provider, `src/Auth/DeviceAwareEloquentUserProvider.php`, registered by `DeviceSessionsServiceProvider` under the driver name **`device-aware-eloquent`**. Hosts opt in by setting `'driver' => 'device-aware-eloquent'` on their auth provider in `config/auth.php` — nothing is overridden automatically. Its `retrieveByToken()` / `updateRememberToken()` bind remember tokens to a device row and the device cookie instead of the single `remember_token` column (single active token per device; tokens are sha256-hashed via the `RememberTokenHasher` contract).

Per-request tracking runs through `src/Http/Middleware/TrackAuthenticatedUserDevice.php` (not auto-registered — the host aliases and attaches it). It resolves the device, throttle-touches `last_seen_at`/`last_used_at`, and exposes the device id as the `current_device_id` request attribute.

Device resolution (`src/Actions/ResolveOrCreateUserDeviceFromRequest.php`, the `DeviceResolver` contract) falls back **cookie → session-keyed bootstrap cache → create**. The bootstrap cache bridges the login→two-factor gap before the device cookie is set; keep it when refactoring.

Everything host-facing is a swappable contract (`src/Contracts/`) bound to a `Default*` in `src/Support/`: `DeviceResolver`, `DeviceNameResolver`, `OsFamilyDetector`, `DeviceCookieFactory`, `IpMasker`, `RememberTokenHasher`. New code should resolve these from the container, never reference the `Default*` directly.

Lifecycle is event-driven: `TouchDeviceLastSeen` fires `DeviceTouched` (hosts listen to, e.g., write their own `user.last_seen_at` — the package assumes no such column). An opt-in listener maps Laravel's `OtherDeviceLogout` to `RevokeOtherUserDevices`. The Fortify bridge (`src/Integrations/Fortify/`) is registered only when `class_exists(TwoFactorAuthenticationChallenged)`.

Models (`UserDevice`, `UserDeviceRememberToken`) are swappable via `config('device-sessions.models.*')` and key-type agnostic via the `HasConfigurableKey` trait (id / uuid / ulid from config). The `HasDeviceSessions` trait goes on the host's Authenticatable.

## Things that are easy to get wrong

- `device-sessions.keys.*` and `table_names.*` must be set **before** running the published migrations — the migration files read config at run time. `user_key_type` must match the host's users-table key type or the FK won't line up.
- Migrations are **publish-only** — the service provider does **not** call `loadMigrationsFrom()`, so `vendor:publish --tag=device-sessions-migrations` is the only route into a host app. The publish maps each file individually (`offerMigrationPublishing()`), stamping the target with the publish time — an already published copy keeps its filename, so re-publishing never duplicates a migration. The test suite loads the package path itself in `tests/TestCase.php`.
- Source migrations are named **`<sequence>_<migration>`** — `00001_create_user_devices_table.php` — and carry **no date**. The sequence is the package's own running order and is split off on publish; the consumer sees `2026_08_27_143012_create_user_devices_table.php`, stamped one second per position. A new migration takes the next free number.
- That **sequence is load-bearing**, not cosmetic. The remember-token table carries a foreign key to `user_devices`, so it has to publish behind it. Equal timestamps would put the migrator back on alphabetical order, where `create_user_device_remember_tokens_table` sorts _before_ `create_user_devices_table` and the foreign key breaks. `MigrationPublishingTest` asserts both the source names and the resulting order.
- Because the published names are stamped at publish time, the tables land **after** the host's users table whatever its own timestamp is — the old `0001_01_01_*` prefixes sorted them ahead of a users table stamped later, which broke the FK on a fresh install.
- The package never `require`s Fortify. The bridge class type-hints the Fortify event but is only registered (and thus loaded) when Fortify is installed. Keep the `class_exists` guard.
- Device timestamps: `last_seen_at` = any activity (throttled); `last_used_at` = activity + remember-token use. Both are kept on purpose.
- Tests use Testbench; there is no `bootstrap/app.php`. Add new setup to `tests/TestCase.php` / `tests/Pest.php`. The fixture User uses `HasDeviceSessions` + UUID keys.
