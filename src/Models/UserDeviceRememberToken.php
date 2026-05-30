<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use KirchDev\DeviceSessions\Concerns\HasConfigurableKey;

/**
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class UserDeviceRememberToken extends Model
{
    use HasConfigurableKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_device_id',
        'token_hash',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('device-sessions.table_names.remember_tokens', 'user_device_remember_tokens');
    }

    /**
     * @return BelongsTo<UserDevice, $this>
     */
    public function device(): BelongsTo
    {
        /** @var class-string<UserDevice> $deviceModel */
        $deviceModel = config('device-sessions.models.device', UserDevice::class);

        return $this->belongsTo($deviceModel, 'user_device_id');
    }
}
