<?php

namespace App\Policies;

class MenuPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'menus';
    }
}
