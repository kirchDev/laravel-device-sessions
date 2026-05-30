<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use KirchDev\DeviceSessions\Concerns\HasConfigurableKey;
use KirchDev\DeviceSessions\Enums\DeviceOsFamily;
use KirchDev\DeviceSessions\Enums\DeviceType;

/**
 * @property DeviceType $type
 * @property DeviceOsFamily $os_family
 * @property string $name
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property Carbon|null $last_used_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 */
class UserDevice extends Model
{
    use HasConfigurableKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'type',
        'os_family',
        'name',
        'user_agent',
        'ip_address',
        'last_used_at',
        'last_seen_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'os_family' => DeviceOsFamily::class,
            'last_used_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('device-sessions.table_names.devices', 'user_devices');
    }

    public function isFillable($key): bool
    {
        if ($key === config('device-sessions.column_names.user_foreign_key', 'user_id')) {
            return true;
        }

        return parent::isFillable($key);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('device-sessions.models.user') ?: config('auth.providers.users.model');
        $foreignKey = config('device-sessions.column_names.user_foreign_key', 'user_id');

        return $this->belongsTo($userModel, $foreignKey);
    }

    /**
     * @return HasMany<UserDeviceRememberToken, $this>
     */
    public function rememberTokens(): HasMany
    {
        /** @var class-string<UserDeviceRememberToken> $tokenModel */
        $tokenModel = config('device-sessions.models.remember_token', UserDeviceRememberToken::class);

        return $this->hasMany($tokenModel, 'user_device_id');
    }

    /**
     * @param  Builder<UserDevice>  $query
     * @return Builder<UserDevice>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * @param  Builder<UserDevice>  $query
     * @return Builder<UserDevice>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }
}
