<?php

namespace App\Policies;

use App\Models\Gallery;
use App\Models\User;

class GalleryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_gallery');
    }

    public function view(User $user, Gallery $gallery): bool
    {
        return $user->can('view_gallery');
    }

    public function create(User $user): bool
    {
        return $user->can('create_gallery');
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->can('update_gallery');
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->can('delete_gallery');
    }
}
