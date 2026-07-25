<?php

namespace App\Policies;

class ServicePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'services';
    }
}
