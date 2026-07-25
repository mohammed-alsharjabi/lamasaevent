<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ServiceCategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'service-categories';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof ServiceCategory
            && parent::delete($user, $model)
            && blank($model->legacy_path)
            && ! $model->services()->exists();
    }
}
