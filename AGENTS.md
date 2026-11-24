# laravel-device-sessions

Standalone Composer library (no host app) that adds device-bound login sessions to Laravel 13 on PHP 8.4+. Tests run on `orchestra/testbench`: in-memory SQLite locally, PostgreSQL as well in CI (`DB_CONNECTION`).

## Commands

- Gate: `pnpm check && composer larastan && composer test` (`verify` in `.tituskirch-skills.json`). One test: `composer test -- --filter=Name`. Style: `composer pint`, `composer pint:fix`.
- Husky runs lint-staged (Pint, Larastan, oxlint, oxfmt) and commitlint. Never `--no-verify` unless asked.
- Conventional Commits. Work lands on `dev`; release-please releases from `main`.

## Architecture

- `DeviceAwareEloquentUserProvider` is the auth driver `device-aware-eloquent`. Hosts opt in via `config/auth.php`; nothing is overridden. Remember tokens bind to a device row and the device cookie, one active token per device, hashed via `RememberTokenHasher`.
- `TrackAuthenticatedUserDevice` is not auto-registered. It sets the `current_device_id` request attribute.
- Device resolution falls back cookie → session-keyed bootstrap cache → create. The cache bridges login → two-factor before the cookie exists; keep it.
- Host-facing services are contracts in `src/Contracts/`, bound in `DeviceSessionsServiceProvider::packageRegistered()`. Resolve them from the container, never the concrete class.
- `TouchDeviceLastSeen` (throttled) writes both `last_seen_at` and `last_used_at` on the device and fires `DeviceTouched`; a remember-token login stamps the token row's own `last_used_at`. The package assumes no `users.last_seen_at`. Keep both device columns.
- The `OtherDeviceLogout` → revoke-other-devices listener is toggled by `device-sessions.events.*` and on by default.
- Fortify is never required: the bridge in `src/Integrations/Fortify/` registers only behind `class_exists(TwoFactorAuthenticationChallenged::class)`. Keep the guard.
- Models are swappable via `device-sessions.models.*`, key type (id / uuid / ulid) via `HasConfigurableKey`. Hosts put `HasDeviceSessions` on their Authenticatable.

## Pitfalls

- The provider extends Spatie's `PackageServiceProvider`: bindings in `packageRegistered()`, wiring in `packageBooted()`, and `configurePackage()` holds only config, command and migrations. Booting it by hand in a test needs `->register()->boot()`.
- Migrations are publish-only (`runsMigrations()` off); `vendor:publish --tag=device-sessions-migrations` is the only route into a host. The `bootPackageMigrations()` override returns early outside the console to skip upstream's directory scan per migration.
- Migrations read `device-sessions.keys.*` and `table_names.*` at run time, so hosts set them before migrating. `user_key_type` must match the users table's key type.
- Source migrations are named `0001_01_01_<sequence>_<name>.php`; a new one takes the next sequence. Publishing strips the prefix and stamps the publish time, so the sequence is the only thing ordering them — the remember-token table's foreign key needs `user_devices` first. `MigrationPublishingTest` guards names and order.
- Published paths contain a literal `/./`; normalise it in assertions (`normalisePath()`).
- Test setup goes in `tests/TestCase.php` / `tests/Pest.php`; there is no `bootstrap/app.php`. The fixture User uses `HasDeviceSessions` and UUID keys. After `migrate:fresh`, call `TestCase::restoreBaselineSchema()`, not the package path alone — PostgreSQL rejects the re-migrate.
- `resources/boost/` ships to consumers. Laravel Boost finds it by path only (`resources/boost/{guidelines,skills}`), so a rename drops it silently. Every guideline always loads and one that throws renders empty: keep `$assist` calls within the allowlist `BoostResourcesTest` checks. A `SKILL.md` needs flat `name` / `description` frontmatter or Boost discards it.
