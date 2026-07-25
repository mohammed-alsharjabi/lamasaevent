<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GalleryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'galleries';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Gallery
            && parent::delete($user, $model)
            && ! $model->is_legacy;
    }
}
