<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ArticlePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'articles';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Article
            && parent::delete($user, $model)
            && blank($model->legacy_path);
    }
}
