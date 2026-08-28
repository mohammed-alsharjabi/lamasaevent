<?php

namespace App\Policies;

class SiteSettingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'settings';
    }
}
