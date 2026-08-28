<?php

namespace App\Policies;

class RedirectPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'redirects';
    }
}
