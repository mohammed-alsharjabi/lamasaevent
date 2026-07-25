<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ServicePolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'services';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Service
            && parent::delete($user, $model)
            && blank($model->legacy_path)
            && ! $model->children()->exists();
    }
}
