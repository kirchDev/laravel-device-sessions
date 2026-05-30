<?php

declare(strict_types=1);

use KirchDev\DeviceSessions\Models\UserDevice;
use KirchDev\DeviceSessions\Models\UserDeviceRememberToken;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent models backing the device session tables. Applications may
    | swap these for their own subclasses (for example to add casts or scopes).
    |
    | "user" is the Authenticatable model that owns devices. Leave it null to
    | fall back to the configured auth provider model
    | (config('auth.providers.users.model')).
    |
    */

    'models' => [
        'user' => null,
        'device' => UserDevice::class,
        'remember_token' => UserDeviceRememberToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Change these if your application already owns tables with the default
    | names, or if you publish and customise the migrations. "users" is the
    | referenced table for the device foreign key.
    |
    */

    'table_names' => [
        'users' => 'users',
        'devices' => 'user_devices',
        'remember_tokens' => 'user_device_remember_tokens',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    */

    'column_names' => [
        'user_foreign_key' => 'user_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Types
    |--------------------------------------------------------------------------
    |
    | Column types used by the package migrations and models. Configure them
    | before running the migrations. "primary_key_type" controls the device and
    | remember-token primary keys; "user_key_type" must match the key type of
    | your users table so the foreign key lines up.
    |
    | Supported: "id", "uuid", "ulid"
    |
    */

    'keys' => [
        'primary_key_type' => 'id',
        'user_key_type' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Cookie
    |--------------------------------------------------------------------------
    |
    | The long-lived cookie that binds a browser to a device row. "secure" of
    | null derives from config('session.secure') and falls back to
    | production-or-HTTPS. "lifetime" is in minutes.
    |
    */

    'cookie' => [
        'name' => 'device',
        'lifetime' => 60 * 24 * 365,
        'path' => '/',
        'same_site' => 'lax',
        'secure' => null,
        'http_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | "bootstrap_ttl" bridges the login -> two-factor gap: the resolved device
    | id is cached per (user, session) until the device cookie is set.
    | "last_seen_throttle" debounces the per-request last-seen touch. Both are
    | in seconds. "store" of null uses the default cache store.
    |
    */

    'cache' => [
        'store' => null,
        'key_prefix' => 'device_sessions',
        'bootstrap_ttl' => 60,
        'last_seen_throttle' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remember Tokens
    |--------------------------------------------------------------------------
    |
    | "lifetime" (in minutes) sets the expires_at on newly issued device-bound
    | remember tokens. null means tokens never expire (only revocation or a new
    | login rotation invalidates them).
    |
    */

    'remember' => [
        'lifetime' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle Events
    |--------------------------------------------------------------------------
    |
    | When enabled, the package listens on Laravel core auth events. The
    | OtherDeviceLogout listener revokes every other device for the user when
    | Laravel's logoutOtherDevices() fires. Device tracking itself runs through
    | the middleware and auth provider regardless of this flag.
    |
    */

    'events' => [
        'enabled' => true,
        'revoke_other_devices_on_other_device_logout' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | device-sessions:prune deletes devices revoked more than this many days
    | ago (and their remember tokens via cascade). The command ships unscheduled
    | — wire it into your application's scheduler.
    |
    */

    'prune' => [
        'retention_days' => 180,
    ],

];
