<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use KirchDev\DeviceSessions\Concerns\HasDeviceSessions;

class UlidUser extends Authenticatable
{
    use HasDeviceSessions;
    use HasUlids;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
