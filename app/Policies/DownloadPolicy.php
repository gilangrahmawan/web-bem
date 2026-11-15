<?php

namespace App\Policies;

use App\Models\Download;
use App\Models\User;

class DownloadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_download');
    }

    public function view(User $user, Download $download): bool
    {
        return $user->can('view_download');
    }

    public function create(User $user): bool
    {
        return $user->can('create_download');
    }

    public function update(User $user, Download $download): bool
    {
        return $user->can('update_download');
    }

    public function delete(User $user, Download $download): bool
    {
        return $user->can('delete_download');
    }
}
