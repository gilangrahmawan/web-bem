<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

/** Template dokumen: kelola = folder.manage (Sekretaris; super_admin lewat Gate::before). */
class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('folder.manage');
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return $this->viewAny($user);
    }
}
