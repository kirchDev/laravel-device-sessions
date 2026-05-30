<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use KirchDev\DeviceSessions\Contracts\DeviceCookieFactory;
use KirchDev\DeviceSessions\Contracts\DeviceResolver;
use KirchDev\DeviceSessions\Contracts\RememberTokenHasher;
use KirchDev\DeviceSessions\Enums\DeviceType;
use KirchDev\DeviceSessions\Models\UserDeviceRememberToken;
use KirchDev\DeviceSessions\Support\DeviceSessions;
use Throwable;

/**
 * Eloquent user provider whose remember-me tokens are bound to a device row and
 * the device cookie, instead of the single `remember_token` column. Opt in via
 * 'driver' => 'device-aware-eloquent' on the auth provider in config/auth.php.
 */
class DeviceAwareEloquentUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        string $model,
        private readonly DeviceResolver $deviceResolver,
        private readonly DeviceCookieFactory $cookieFactory,
        private readonly RememberTokenHasher $tokenHasher,
    ) {
        parent::__construct($hasher, $model);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $user = $this->retrieveById($identifier);

        if ($user === null) {
            return null;
        }

        $request = $this->resolveRequest();

        if (! $request instanceof Request) {
            return null;
        }

        $deviceCookie = $request->cookie(DeviceSessions::cookieName());

        if (! is_string($deviceCookie) || trim($deviceCookie) === '') {
            return null;
        }

        $deviceId = trim($deviceCookie);
        $tokenModel = DeviceSessions::rememberTokenModel();
        $userForeignKey = DeviceSessions::userForeignKey();

        $rememberToken = $tokenModel::query()
            ->where('token_hash', $this->tokenHasher->hash($token))
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereHas('device', function (Builder $query) use ($user, $deviceId, $userForeignKey): void {
                $query->where($userForeignKey, $user->getAuthIdentifier())
                    ->where('type', DeviceType::Web)
                    ->whereKey($deviceId)
                    ->whereNull('revoked_at');
            })
            ->first();

        if (! $rememberToken instanceof UserDeviceRememberToken) {
            return null;
        }

        $rememberToken->forceFill(['last_used_at' => now()])->save();

        return $user;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        if (! is_string($token) || trim($token) === '') {
            return;
        }

        $request = $this->resolveRequest();

        if (! $request instanceof Request) {
            return;
        }

        $device = $this->deviceResolver->resolveOrCreate($user, $request);

        $resolvedDeviceId = (string) $device->getKey();
        $cookieValue = $request->cookie(DeviceSessions::cookieName());
        $normalizedCookieValue = is_string($cookieValue) ? trim($cookieValue) : '';

        if ($normalizedCookieValue === '' || $normalizedCookieValue !== $resolvedDeviceId) {
            Cookie::queue($this->cookieFactory->make($request, $resolvedDeviceId));
        }

        $device->rememberTokens()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $device->rememberTokens()->create([
            'token_hash' => $this->tokenHasher->hash($token),
            'last_used_at' => now(),
            'expires_at' => $this->rememberTokenExpiry(),
        ]);
    }

    private function rememberTokenExpiry(): ?Carbon
    {
        $lifetime = config('device-sessions.remember.lifetime');

        return is_numeric($lifetime) ? now()->addMinutes((int) $lifetime) : null;
    }

    private function resolveRequest(): ?Request
    {
        try {
            $request = request();

            return $request instanceof Request ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }
}
