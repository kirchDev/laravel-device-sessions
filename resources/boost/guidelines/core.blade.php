# Laravel Device Sessions

`kirchdev/laravel-device-sessions` gives an application a per-device session list ("where am I
signed in"), device-bound remember-me tokens and privacy-respecting IP handling. Everything is
configured in `config/device-sessions.php`.

## Setup order (do not reorder)
- Publish the config first: `{{ $assist->artisanCommand('vendor:publish --tag=device-sessions-config') }}`.
- Set `device-sessions.keys.*` (`id` / `uuid` / `ulid`) and `table_names.*` **before** migrating — the
  migrations read the config at run time, and `keys.user_key_type` must match the users-table primary
  key or the foreign key will not line up.
- The package ships migrations but never loads them. Publish them once, then migrate:
  `{{ $assist->artisanCommand('vendor:publish --tag=device-sessions-migrations') }}` and
  `{{ $assist->artisanCommand('migrate') }}`.
- Add the `KirchDev\DeviceSessions\Concerns\HasDeviceSessions` trait to the authenticatable model.
- Point its auth provider at the device-aware driver: in `config/auth.php`, the users provider's
  `driver` becomes `device-aware-eloquent` instead of `eloquent`. Without this, remember-me stays on
  the single `remember_token` column and nothing is device-bound.
- Alias `KirchDev\DeviceSessions\Http\Middleware\TrackAuthenticatedUserDevice` and attach it to the
  authenticated routes. It is what populates the device list and exposes the `current_device_id`
  request attribute.

## Managing devices
- The package ships **no routes**. Every operation is a plain action, invoked from the application's
  own controllers: `ListUserDevices`, `RevokeUserDevice`, `RevokeOtherUserDevices`,
  `UpdateUserDeviceName` — all under `KirchDev\DeviceSessions\Actions`, all with `execute(...)`.
- Read the current device through `$user->currentDevice()` / `$user->currentDeviceId()` or the
  `current_device_id` request attribute; do not re-derive it from the cookie.
- Revoked devices are retained for an audit window and removed by `device-sessions:prune`, which
  ships **unscheduled** — the application wires it into its own scheduler.

## Extending it
- Resolve the models through `config('device-sessions.models.*')` — user, device and remember token
  are all swappable.
- Customise behaviour by rebinding a contract in `KirchDev\DeviceSessions\Contracts`
  (`IpMasker`, `DeviceNameResolver`, `OsFamilyDetector`, `DeviceCookieFactory`, `DeviceResolver`,
  `RememberTokenHasher`), never by extending or patching the shipped `Default*` classes.
- React to activity with the `KirchDev\DeviceSessions\Events\DeviceTouched` event rather than
  reaching into the touch path.
