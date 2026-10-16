<?php

namespace App\Policies;

use App\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(MediaPolicy::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(MediaPolicy::MANAGE_FOLDERS);
    }

    public function update(User $user, MediaFolder $folder): bool
    {
        return $user->can(MediaPolicy::MANAGE_FOLDERS);
    }

    public function delete(User $user, MediaFolder $folder): bool
    {
        return $user->can(MediaPolicy::MANAGE_FOLDERS);
    }
}
