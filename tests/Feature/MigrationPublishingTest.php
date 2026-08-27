<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use KirchDev\DeviceSessions\DeviceSessionsServiceProvider;

it('does not register the package migrations on the application migrator', function () {
    $packagePath = realpath(__DIR__.'/../../database/migrations');

    $registered = array_map(
        fn (string $path): string => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain($packagePath);
});

it('offers the migrations to consumers through the publish tag', function () {
    $paths = ServiceProvider::pathsToPublish(
        DeviceSessionsServiceProvider::class,
        'device-sessions-migrations',
    );

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../database/migrations'))
        ->and(array_values($paths)[0])->toBe(database_path('migrations'));
});

it('publishes the migrations without re-stamping their filenames', function () {
    $stamped = array_map(
        fn (string $path): string => realpath($path) ?: $path,
        ServiceProvider::publishableMigrationPaths(),
    );

    expect($stamped)->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('migrates the package tables from the package migration path in the test suite', function () {
    expect(Schema::hasTable(config('device-sessions.table_names.devices')))->toBeTrue()
        ->and(Schema::hasTable(config('device-sessions.table_names.remember_tokens')))->toBeTrue();
});
