<?php

namespace App\Policies;

class ContactSettingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'settings';
    }
}
