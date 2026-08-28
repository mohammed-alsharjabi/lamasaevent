<?php

namespace App\Policies;

use App\Models\PublishJob;
use App\Models\User;

class PublishJobPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('publish-jobs.view');
    }

    public function view(User $user, PublishJob $publishJob): bool
    {
        return $user->hasPermission('publish-jobs.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PublishJob $publishJob): bool
    {
        return false;
    }

    public function delete(User $user, PublishJob $publishJob): bool
    {
        return false;
    }
}
