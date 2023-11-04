<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use InitAfricaHQ\Cashier\Billable;
use Tests\Support\Factories\UserFactory;

class User extends Authenticatable
{
    use Billable, HasFactory;

    protected static function newFactory()
    {
        return new UserFactory();
    }
}
