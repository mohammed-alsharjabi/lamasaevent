<?php

namespace App\Policies;

class SitemapEntryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'sitemap';
    }
}
