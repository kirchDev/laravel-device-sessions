<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions;

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DeviceSessionsServiceProvider extends PackageServiceProvider
{
    /**
     * The package's shape: the config file, the command and the migrations.
     *
     * Migrations are discovered from database/migrations rather than listed here, so the
     * running order lives in the filenames and adding one is a single file. They are named
     * with Laravel's own sentinel date (0001_01_01_000001_create_user_devices_table.php): the
     * publish strips that prefix and stamps its own, and in the meantime it keeps the source
     * files sorting in dependency order for the suite, which migrates them from the package
     * path. runsMigrations() stays off — consumers publish, the provider never auto-loads.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-device-sessions')
            ->hasConfigFile()
            ->hasCommands(PruneRevokedUserDevicesCommand::class)
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DeviceNameResolver::class, DefaultDeviceNameResolver::class);
        $this->app->singleton(OsFamilyDetector::class, DefaultOsFamilyDetector::class);
        $this->app->singleton(IpMasker::class, DefaultIpMasker::class);
        $this->app->singleton(DeviceCookieFactory::class, DeviceCookieBuilder::class);
        $this->app->singleton(RememberTokenHasher::class, Sha256RememberTokenHasher::class);
        $this->app->singleton(DeviceResolver::class, ResolveOrCreateUserDeviceFromRequest::class);
    }

    public function packageBooted(): void
    {
        $this->registerAuthProvider();
        $this->registerEventListeners();
        $this->registerFortifyBridge();
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
