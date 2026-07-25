<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class ResourcePolicy
{
    abstract protected function resource(): string;

    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allowed($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allowed($user, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allowed($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user, 'delete');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->allowed($user, 'restore');
    }

    public function restoreAny(User $user): bool
    {
        return $this->allowed($user, 'restore');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->allowed($user, 'force-delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->allowed($user, 'force-delete');
    }

    protected function allowed(User $user, string $action): bool
    {
        return $user->is_active
            && $user->hasPermission($this->resource().'.'.$action);
    }
}
