<?php

namespace App\Policies;

class PagePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'pages';
    }
}
