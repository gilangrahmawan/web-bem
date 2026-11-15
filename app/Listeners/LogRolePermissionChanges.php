<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mencatat perubahan role & permission-nya yang dilakukan pengguna dari dashboard.
 * Perubahan dari seeder/console (tanpa user login) tidak dicatat.
 */
class LogRolePermissionChanges
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function onAttached(PermissionAttachedEvent $event): void
    {
        $this->logPermissions('role.permissions_added', $event->model, $event->permissionsOrIds);
    }

    public function onDetached(PermissionDetachedEvent $event): void
    {
        $this->logPermissions('role.permissions_removed', $event->model, $event->permissionsOrIds);
    }

    public function logRole(string $action, Role $role): void
    {
        if (! Auth::check()) {
            return;
        }

        $this->logger->log($action, $role, ['name' => $role->name]);
    }

    private function logPermissions(string $action, mixed $model, mixed $permissionsOrIds): void
    {
        if (! Auth::check() || ! $model instanceof Role) {
            return;
        }

        $ids = collect(Arr::wrap($permissionsOrIds instanceof Collection ? $permissionsOrIds->all() : $permissionsOrIds))
            ->map(fn ($item) => $item instanceof Permission ? $item->getKey() : $item)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $this->logger->log($action, $role = $model, [
            'role' => $role->name,
            'permissions' => Permission::query()->whereKey($ids)->pluck('name')->all(),
        ]);
    }
}
