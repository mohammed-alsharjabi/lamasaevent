<?php

namespace App\Policies;

class ArticleCategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'article-categories';
    }
}
