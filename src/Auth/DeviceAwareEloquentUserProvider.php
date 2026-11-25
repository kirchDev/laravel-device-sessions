<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->withoutColumnToken(parent::retrieveById($identifier));
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return $this->withoutColumnToken(parent::retrieveByCredentials($credentials));
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

        // The guard has just set the plaintext token on the model, which marks the column
        // dirty: any later save() in this request would persist it into the users table.
        $this->forgetColumnChange($user);
    }

    /**
     * The users table's own remember_token column is dead under this driver, but a value
     * left over from before the switch makes Laravel skip issuing a token at login
     * (SessionGuard::ensureRememberTokenIsSet), so no device token is ever bound. Clear
     * it once, in the row and on the loaded model, without touching timestamps or events.
     *
     * @template TUser of Authenticatable
     *
     * @param  TUser|null  $user
     * @return TUser|null
     */
    private function withoutColumnToken(?Authenticatable $user): ?Authenticatable
    {
        if (! $user instanceof Model) {
            return $user;
        }

        $column = $user->getRememberTokenName();

        if ($column === '' || ! array_key_exists($column, $user->getAttributes()) || $user->getAttributes()[$column] === null) {
            return $user;
        }

        $user->newQueryWithoutScopes()->whereKey($user->getKey())->toBase()->update([$column => null]);
        $user->setAttribute($column, null);
        $user->syncOriginalAttribute($column);

        return $user;
    }

    private function forgetColumnChange(Authenticatable $user): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $column = $user->getRememberTokenName();

        if ($column !== '' && array_key_exists($column, $user->getAttributes())) {
            $user->syncOriginalAttribute($column);
        }
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
