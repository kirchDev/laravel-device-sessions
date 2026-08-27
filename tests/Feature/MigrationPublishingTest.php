<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use KirchDev\DeviceSessions\DeviceSessionsServiceProvider;

/**
 * Strip the timestamp a published migration carries, leaving the part that identifies
 * which table it creates.
 */
function deviceSessionsMigrationTable(string $file): string
{
    return (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file));
}

function deviceSessionsPublishedMigrations(): array
{
    return ServiceProvider::pathsToPublish(
        DeviceSessionsServiceProvider::class,
        'device-sessions-migrations',
    );
}

it('does not register the package migrations on the application migrator', function () {
    $packagePath = realpath(__DIR__.'/../../database/migrations');

    $registered = array_map(
        fn (string $path): string => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain($packagePath);
});

it('offers every package migration under the publish tag', function () {
    $published = deviceSessionsPublishedMigrations();

    expect($published)->toHaveCount(2);

    $sources = array_map(fn (string $path): string => basename($path), array_keys($published));
    sort($sources);

    // The sequence prefix is the package's running order and is stripped on publish, so it has
    // to stay in step with the dependency order asserted below.
    expect($sources)->toBe([
        '00001_create_user_devices_table.php',
        '00002_create_user_device_remember_tokens_table.php',
    ]);

    foreach ($published as $source => $target) {
        expect(is_file($source))->toBeTrue()
            ->and(dirname($target))->toBe(database_path('migrations'))
            ->and(basename($target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_\w+_table\.php$/');
    }
});

it('publishes the migrations in dependency order', function () {
    // The remember-token table carries a foreign key to user_devices. Publishing both under one
    // timestamp would leave the migrator sorting them alphabetically, and
    // create_user_device_remember_tokens_table sorts before create_user_devices_table — the
    // foreign key would then point at a table that does not exist yet.
    $targets = array_map(
        fn (string $path): string => basename($path),
        array_values(deviceSessionsPublishedMigrations()),
    );

    sort($targets);

    expect(array_map('deviceSessionsMigrationTable', $targets))->toBe([
        'create_user_devices_table.php',
        'create_user_device_remember_tokens_table.php',
    ]);
});

it('reuses an already published migration instead of stamping a second copy', function () {
    $database = sys_get_temp_dir().'/device-sessions-publish-'.bin2hex(random_bytes(6));
    mkdir($database.'/migrations', 0o777, true);

    $existing = $database.'/migrations/2020_01_01_000000_create_user_devices_table.php';
    touch($existing);

    $this->app->useDatabasePath($database);
    (new DeviceSessionsServiceProvider($this->app))->boot();

    $devices = null;
    foreach (deviceSessionsPublishedMigrations() as $source => $target) {
        if (str_ends_with($source, '_create_user_devices_table.php')) {
            $devices = $target;
        }
    }

    expect($devices)->toBe($existing);

    unlink($existing);
    rmdir($database.'/migrations');
    rmdir($database);
});

it('migrates the package tables from the package migration path in the test suite', function () {
    expect(Schema::hasTable(config('device-sessions.table_names.devices')))->toBeTrue()
        ->and(Schema::hasTable(config('device-sessions.table_names.remember_tokens')))->toBeTrue();
});
