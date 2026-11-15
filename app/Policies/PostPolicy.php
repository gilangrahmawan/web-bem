<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\RoleName;
use App\Models\Post;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Konten Medpub (AGENTS.md C3, D3, D4 + keputusan pemilik Fase 6).
 *
 * - Tipe yang boleh dibuat: post.create → Artikel & Postingan; post.create_press_release → Press Release;
 *   post.create_study → Kajian Publik.
 * - Ubah: hanya penulis, hanya saat draft/revisi (berlaku juga bagi super_admin).
 * - Tahap koordinator: pemegang post.approve_coordinator yang BUKAN super_admin dan bukan penulis.
 * - Tahap Ketua/Wakil: super_admin yang bukan penulis (cukup satu).
 * - Terbit & tandai sudah diposting: post.publish. Turunkan & revisi: Koordinator Medpub atau super_admin.
 * - Lihat: draft/revisi hanya penulis, Koordinator Medpub, super_admin; selebihnya semua pengurus (read-only).
 */
class PostPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['update', 'submit', 'approveCoordinator', 'approveLeader'];
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Post $post): bool
    {
        return $user->is_active && (
            self::seesAllDrafts($user)
            || $post->author_id === $user->getKey()
            || ! in_array($post->status, [PostStatus::Draft, PostStatus::Revision], true)
        );
    }

    public function create(User $user): bool
    {
        return self::allowedTypes($user) !== [];
    }

    public function createType(User $user, PostType $type): bool
    {
        return in_array($type, self::allowedTypes($user), true);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->is_active && $this->isAuthorWithDraft($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->is_active && $this->isAuthorWithDraft($user, $post);
    }

    public function submit(User $user, Post $post): bool
    {
        return $user->is_active && $this->isAuthorWithDraft($user, $post);
    }

    public function approveCoordinator(User $user, Post $post): bool
    {
        return $user->is_active
            && $post->status === PostStatus::PendingCoordinator
            && self::isCoordinatorApprover($user)
            && $post->author_id !== $user->getKey();
    }

    public function approveLeader(User $user, Post $post): bool
    {
        return $user->is_active
            && $post->status === PostStatus::PendingLeader
            && $user->hasRole(RoleName::SuperAdmin->value)
            && $post->author_id !== $user->getKey();
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->is_active && $post->status === PostStatus::Approved && $user->can('post.publish');
    }

    public function markSocialPosted(User $user, Post $post): bool
    {
        return $user->is_active && $post->status === PostStatus::Published && $user->can('post.publish');
    }

    public function takeDown(User $user, Post $post): bool
    {
        return $user->is_active && $post->status === PostStatus::Published
            && ($user->isSuperAdmin() || self::isCoordinatorApprover($user));
    }

    /** @return list<PostType> */
    public static function allowedTypes(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [PostType::Article, PostType::Social, PostType::PressRelease, PostType::Study];
        }

        return array_values(array_filter([
            $user->checkPermissionTo('post.create') ? PostType::Article : null,
            $user->checkPermissionTo('post.create') ? PostType::Social : null,
            $user->checkPermissionTo('post.create_press_release') ? PostType::PressRelease : null,
            $user->checkPermissionTo('post.create_study') ? PostType::Study : null,
        ]));
    }

    /** Penyetuju tahap koordinator (Koordinator Medpub); super_admin tidak dihitung. */
    public static function isCoordinatorApprover(User $user): bool
    {
        return ! $user->isSuperAdmin() && $user->checkPermissionTo('post.approve_coordinator');
    }

    public static function seesAllDrafts(User $user): bool
    {
        return $user->isSuperAdmin() || self::isCoordinatorApprover($user);
    }

    private function isAuthorWithDraft(User $user, Post $post): bool
    {
        return $post->author_id === $user->getKey()
            && in_array($post->status, [PostStatus::Draft, PostStatus::Revision], true);
    }
}
