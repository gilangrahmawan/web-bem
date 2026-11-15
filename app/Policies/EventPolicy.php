<?php

namespace App\Policies;

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Event;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Agenda kalender (AGENTS.md C4, D4 + keputusan pemilik Fase 3).
 *
 * - Kategori → permission: lihat EventCategory::requiredPermission().
 * - Agenda divisi (rapat, internal, tugas konten): koordinator hanya divisinya.
 *   Pengelola lintas divisi (super_admin, event.manage_all, sekretaris lewat event.create_public)
 *   boleh divisi mana pun atau "umum" (tanpa divisi).
 * - Agenda publik & UKM tidak dibatasi divisi: semua pemegang permission kategorinya boleh mengubah.
 * - Pembina hanya melihat.
 * - Cuti bersama (collective_leave) hanya dari HolidaySeeder: create/update/delete ditolak untuk siapa pun,
 *   TERMASUK super_admin (EnforcedForSuperAdmin). Kategori lain tetap lolos bagi super_admin karena
 *   cek permission di dalamnya diloloskan Gate::before.
 */
class EventPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['create', 'update', 'delete'];
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Event $event): bool
    {
        return Event::query()->whereKey($event->getKey())->visibleTo($user)->exists();
    }

    /** Tanpa kategori: apakah user boleh menambah agenda kategori apa pun. */
    public function create(User $user, EventCategory|string|null $category = null): bool
    {
        if ($category === null) {
            return self::creatableCategories($user) !== [];
        }

        $category = $category instanceof EventCategory ? $category : EventCategory::tryFrom($category);

        return $category !== null
            && ! $category->isSeederOnly()
            && $user->can($category->requiredPermission());
    }

    /** Impor CSV kalender akademik/hari peringatan: super_admin & sekretaris. */
    public function import(User $user): bool
    {
        return $user->can('event.create_public');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->manages($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->manages($user, $event);
    }

    /**
     * Apakah user boleh menaruh agenda kategori ini di divisi tersebut (null = umum).
     */
    public static function canUseDivision(User $user, EventCategory $category, ?int $divisionId): bool
    {
        if (! $category->isDivisionScoped()) {
            return true;
        }

        if (self::managesAllDivisions($user)) {
            return true;
        }

        return $user->division_id !== null && $divisionId === $user->division_id;
    }

    /** @return list<EventCategory> */
    public static function creatableCategories(User $user): array
    {
        return array_values(array_filter(
            EventCategory::cases(),
            fn (EventCategory $category): bool => ! $category->isSeederOnly() && $user->can($category->requiredPermission()),
        ));
    }

    /** Pengelola lintas divisi untuk rapat/internal/tugas konten. */
    public static function managesAllDivisions(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->checkPermissionTo('event.manage_all')
            || $user->checkPermissionTo('event.create_public');
    }

    /** Melihat seluruh agenda di kalender dashboard. */
    public static function seesAllEvents(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(RoleName::Pembina->value)
            || $user->checkPermissionTo('event.manage_all');
    }

    /** Melihat semua tugas konten (bukan hanya yang ditugaskan padanya). */
    public static function seesAllContentTasks(User $user): bool
    {
        return $user->checkPermissionTo('event.assign_task')
            || $user->division?->slug === 'medpub';
    }

    private function manages(User $user, Event $event): bool
    {
        return ! $event->category->isSeederOnly()
            && $user->can($event->category->requiredPermission())
            && self::canUseDivision($user, $event->category, $event->division_id);
    }
}
