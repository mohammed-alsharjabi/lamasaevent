<?php

namespace App\Policies;

class UserPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'users';
    }
}
