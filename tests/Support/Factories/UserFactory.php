<?php

namespace Tests\Support\Factories;

use Orchestra\Testbench\Factories\UserFactory as OrchestraUserFactory;
use Tests\Support\User;

class UserFactory extends OrchestraUserFactory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = User::class;
}
