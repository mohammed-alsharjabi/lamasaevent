<?php

namespace App\Policies;

class GalleryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'galleries';
    }
}
