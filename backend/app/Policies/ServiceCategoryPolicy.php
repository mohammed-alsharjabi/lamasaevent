<?php

namespace App\Policies;

class ServiceCategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'service-categories';
    }
}
