<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use KirchDev\DeviceSessions\DeviceSessionsServiceProvider;
use KirchDev\DeviceSessions\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [DeviceSessionsServiceProvider::class];
    }

    /**
     * Key type under test (id / uuid / ulid). Variants override this.
     */
    protected function deviceKeyType(): string
    {
        return 'id';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return User::class;
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConfig());

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'device-aware-eloquent',
            'model' => $this->userModelClass(),
        ]);

        $app['config']->set('device-sessions.models.user', $this->userModelClass());
        $app['config']->set('device-sessions.keys.primary_key_type', $this->deviceKeyType());
        $app['config']->set('device-sessions.keys.user_key_type', $this->deviceKeyType());
    }

    /**
     * @return array<string, mixed>
     */
    protected function databaseConfig(): array
    {
        $driver = getenv('DB_CONNECTION') ?: 'sqlite';

        return match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'device_sessions_test',
                'username' => getenv('DB_USERNAME') ?: 'device_sessions',
                'password' => getenv('DB_PASSWORD') ?: 'device_sessions',
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Persistent drivers (e.g. pgsql) keep tables between tests, unlike the
        // fresh-per-connection in-memory SQLite default. Drop everything first so
        // each test starts from a clean schema.
        Schema::dropAllTables();

        $this->createUsersTable();
        $this->artisan('migrate')->run();
    }

    private function createUsersTable(): void
    {
        $keyType = $this->deviceKeyType();

        Schema::create('users', function (Blueprint $table) use ($keyType): void {
            match ($keyType) {
                'uuid' => $table->uuid('id')->primary(),
                'ulid' => $table->ulid('id')->primary(),
                default => $table->id(),
            };
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }
}
