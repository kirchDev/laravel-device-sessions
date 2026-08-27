<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions;

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use KirchDev\DeviceSessions\Actions\ResolveOrCreateUserDeviceFromRequest;
use KirchDev\DeviceSessions\Auth\DeviceAwareEloquentUserProvider;
use KirchDev\DeviceSessions\Console\PruneRevokedUserDevicesCommand;
use KirchDev\DeviceSessions\Contracts\DeviceCookieFactory;
use KirchDev\DeviceSessions\Contracts\DeviceNameResolver;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Contracts\IpMasker;
use KirchDev\DeviceSessions\Contracts\OsFamilyDetector;
use KirchDev\DeviceSessions\Contracts\RememberTokenHasher;
use KirchDev\DeviceSessions\Integrations\Fortify\QueueDeviceCookieOnTwoFactorChallenge;
use KirchDev\DeviceSessions\Listeners\RevokeDevicesOnOtherDeviceLogout;
use KirchDev\DeviceSessions\Support\DefaultDeviceNameResolver;
use KirchDev\DeviceSessions\Support\DefaultIpMasker;
use KirchDev\DeviceSessions\Support\DefaultOsFamilyDetector;
use KirchDev\DeviceSessions\Support\DeviceCookieBuilder;
use KirchDev\DeviceSessions\Support\DeviceSessions;
use KirchDev\DeviceSessions\Support\Sha256RememberTokenHasher;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class DeviceSessionsServiceProvider extends ServiceProvider
{
    /**
     * The package's migrations, in the order they have to run: the remember-token table
     * carries a foreign key to user_devices, so that table must exist first.
     *
     * Published filenames are stamped at publish time, so this list — not the source
     * filenames — is what fixes the order a consumer's migrator ends up seeing. Each
     * entry is published one second after the one before it.
     *
     * @var list<string>
     */
    private const MIGRATIONS = [
        '0001_01_01_000001_create_user_devices_table.php',
        '0001_01_01_000002_create_user_device_remember_tokens_table.php',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/device-sessions.php', 'device-sessions');

        $this->app->singleton(DeviceNameResolver::class, DefaultDeviceNameResolver::class);
        $this->app->singleton(OsFamilyDetector::class, DefaultOsFamilyDetector::class);
        $this->app->singleton(IpMasker::class, DefaultIpMasker::class);
        $this->app->singleton(DeviceCookieFactory::class, DeviceCookieBuilder::class);
        $this->app->singleton(RememberTokenHasher::class, Sha256RememberTokenHasher::class);
        $this->app->singleton(DeviceResolver::class, ResolveOrCreateUserDeviceFromRequest::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/device-sessions.php' => config_path('device-sessions.php'),
        ], 'device-sessions-config');

        $this->offerMigrationPublishing();

        $this->registerAuthProvider();
        $this->registerEventListeners();
        $this->registerFortifyBridge();

        if ($this->app->runningInConsole()) {
            $this->commands([PruneRevokedUserDevicesCommand::class]);
        }
    }

    /**
     * Map every package migration onto the filename it gets inside the consuming application.
     *
     * The migrations are never loaded from the package, so the published copy is the only one
     * that ever runs. Each is stamped with the publish time, one second apart in MIGRATIONS
     * order, which is what keeps the foreign keys resolvable when the consumer migrates.
     */
    private function offerMigrationPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $publishedAt = time();
        $paths = [];

        foreach (self::MIGRATIONS as $offset => $migration) {
            $name = (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migration);

            $paths[__DIR__.'/../database/migrations/'.$migration] = $this->publishedMigrationPath(
                $name,
                $publishedAt + $offset,
            );
        }

        $this->publishes($paths, 'device-sessions-migrations');
    }

    /**
     * Where a published migration lands.
     *
     * An already published copy keeps the filename it has, so re-running the publish never
     * leaves a consumer with two migrations creating the same table. Only a migration that
     * is not there yet gets a fresh stamp.
     */
    private function publishedMigrationPath(string $name, int $timestamp): string
    {
        $directory = database_path('migrations');
        $existing = glob($directory.DIRECTORY_SEPARATOR.'*_'.$name) ?: [];

        return $existing[0] ?? $directory.DIRECTORY_SEPARATOR.date('Y_m_d_His', $timestamp).'_'.$name;
    }

    private function registerAuthProvider(): void
    {
        Auth::provider('device-aware-eloquent', function (Application $app, array $config): DeviceAwareEloquentUserProvider {
            /** @var class-string<Authenticatable> $model */
            $model = $config['model'] ?? DeviceSessions::userModel();

            /** @var Hasher $hasher */
            $hasher = $app->make('hash');

            return new DeviceAwareEloquentUserProvider(
                $hasher,
                $model,
                $app->make(DeviceResolver::class),
                $app->make(DeviceCookieFactory::class),
                $app->make(RememberTokenHasher::class),
            );
        });
    }

    private function registerEventListeners(): void
    {
        if (! (bool) config('device-sessions.events.enabled', true)) {
            return;
        }

        if ((bool) config('device-sessions.events.revoke_other_devices_on_other_device_logout', true)) {
            Event::listen(OtherDeviceLogout::class, RevokeDevicesOnOtherDeviceLogout::class);
        }
    }

    private function registerFortifyBridge(): void
    {
        if (! class_exists(TwoFactorAuthenticationChallenged::class)) {
            return;
        }

        Event::listen(TwoFactorAuthenticationChallenged::class, QueueDeviceCookieOnTwoFactorChallenge::class);
    }
}
