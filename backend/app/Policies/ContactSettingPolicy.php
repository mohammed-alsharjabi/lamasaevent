<?php

namespace App\Policies;

use App\Models\ContactSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContactSettingPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'settings';
    }

    public function create(User $user): bool
    {
        return parent::create($user) && ! ContactSetting::query()->exists();
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
