<?php

namespace App\Policies;

class MediaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'media';
    }
}
