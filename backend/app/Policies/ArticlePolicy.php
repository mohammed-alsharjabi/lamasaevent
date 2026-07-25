<?php

namespace App\Policies;

class ArticlePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'articles';
    }
}
