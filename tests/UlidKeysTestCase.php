<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests;

use Illuminate\Database\Eloquent\Model;
use KirchDev\DeviceSessions\Tests\Fixtures\UlidUser;

abstract class UlidKeysTestCase extends TestCase
{
    protected function deviceKeyType(): string
    {
        return 'ulid';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return UlidUser::class;
    }
}
