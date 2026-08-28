<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MediaPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'media';
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Media
            && parent::delete($user, $model)
            && blank($model->source_path)
            && ! $model->isInUse();
    }
}
