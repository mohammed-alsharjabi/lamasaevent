<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AreaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'areas';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Area
            && parent::delete($user, $model)
            && blank($model->legacy_path);
    }
}
