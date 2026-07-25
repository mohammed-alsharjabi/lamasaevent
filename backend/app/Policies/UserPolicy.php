<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'users';
    }

    public function delete(User $user, Model $model): bool
    {
        if (
            ! $model instanceof User
            || ! parent::delete($user, $model)
            || $user->is($model)
        ) {
            return false;
        }

        if (! $model->hasRole('super-admin')) {
            return true;
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))
            ->whereKeyNot($model->getKey())
            ->exists();
    }
}
