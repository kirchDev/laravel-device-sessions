<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Tests;

use Illuminate\Database\Eloquent\Model;
use KirchDev\DeviceSessions\Tests\Fixtures\UuidUser;

abstract class UuidKeysTestCase extends TestCase
{
    protected function deviceKeyType(): string
    {
        return 'uuid';
    }

    /**
     * @return class-string<Model>
     */
    protected function userModelClass(): string
    {
        return UuidUser::class;
    }
}
