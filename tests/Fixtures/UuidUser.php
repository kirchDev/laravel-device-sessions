<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use KirchDev\DeviceSessions\Concerns\HasDeviceSessions;

class UuidUser extends Authenticatable
{
    use HasDeviceSessions;
    use HasUuids;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
