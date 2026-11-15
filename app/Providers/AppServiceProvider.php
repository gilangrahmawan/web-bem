<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogRolePermissionChanges;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        $this->configurePasswordRules();
        $this->configureSuperAdminGate();
        $this->configureShieldPermissionNames();
        $this->registerEventListeners();
    }

    /** Password minimal 10 karakter: huruf, angka, simbol (AGENTS.md B4). */
    private function configurePasswordRules(): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->numbers()->symbols());
    }

    /**
     * super_admin lolos semua cek, kecuali aksi yang dinyatakan policy sebagai
     * aturan keras (EnforcedForSuperAdmin), mis. menghapus user atau mengedit role super_admin.
     */
    private function configureSuperAdminGate(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->isSuperAdmin()) {
                return null;
            }

            $target = $arguments[0] ?? null;

            if ($target !== null && (is_object($target) || (is_string($target) && class_exists($target)))) {
                $policy = Gate::getPolicyFor($target);

                if ($policy instanceof EnforcedForSuperAdmin
                    && in_array($ability, $policy::superAdminEnforcedAbilities(), true)) {
                    return null;
                }
            }

            return true;
        });
    }

    /**
     * Nama permission resource mengikuti AGENTS.md: `view_any_user`, `create_division`, dst.
     * Permission kustom (`modul.aksi`) dibiarkan apa adanya (return null).
     */
    private function configureShieldPermissionNames(): void
    {
        FilamentShield::buildPermissionKeyUsing(
            function (string $entity, ?string $affix, string $subject): ?string {
                if ($entity === 'custom' || $affix === null) {
                    return null;
                }

                return Str::snake($affix).'_'.Str::snake($subject);
            }
        );
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, [LogAuthenticationActivity::class, 'onLogin']);
        Event::listen(Failed::class, [LogAuthenticationActivity::class, 'onFailed']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'onLogout']);

        Event::listen(PermissionAttachedEvent::class, [LogRolePermissionChanges::class, 'onAttached']);
        Event::listen(PermissionDetachedEvent::class, [LogRolePermissionChanges::class, 'onDetached']);

        Role::created(fn (Role $role) => app(LogRolePermissionChanges::class)->logRole('role.created', $role));
        Role::updated(fn (Role $role) => app(LogRolePermissionChanges::class)->logRole('role.updated', $role));
        Role::deleted(fn (Role $role) => app(LogRolePermissionChanges::class)->logRole('role.deleted', $role));
    }
}
