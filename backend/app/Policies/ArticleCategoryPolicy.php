<?php

namespace App\Policies;

use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ArticleCategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'article-categories';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof ArticleCategory
            && parent::delete($user, $model)
            && ! $model->articles()->exists();
    }
}
