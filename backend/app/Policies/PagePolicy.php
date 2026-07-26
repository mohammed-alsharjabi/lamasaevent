<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'pages';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Page
            && parent::delete($user, $model)
            && blank($model->legacy_path);
    }
}
