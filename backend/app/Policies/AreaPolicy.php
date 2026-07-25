<?php

namespace App\Policies;

class AreaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'areas';
    }
}
