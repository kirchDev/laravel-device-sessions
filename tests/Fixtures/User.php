<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use KirchDev\DeviceSessions\Concerns\HasDeviceSessions;

class User extends Authenticatable
{
    use HasDeviceSessions;

    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
