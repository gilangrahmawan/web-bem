<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Listeners\LogRolePermissionChanges;
use App\Listeners\NotifyBackupProblems;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;
use App\Services\Chat\ConversationSyncService;
use App\Services\Drive\DriveAccessResolver;
use App\Services\KasService;
use App\Services\Letters\DriveLetterArchiver;
use App\Services\Letters\LetterArchiver;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Carbon\Carbon;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu instance per request: ringkasan kas di-memo dan dipakai bersama oleh beberapa widget.
        $this->app->scoped(KasService::class);

        // Arsip surat Humas ber-lampiran ke folder sistem Drive "Surat" (Fase 8).
        $this->app->bind(LetterArchiver::class, DriveLetterArchiver::class);

        // Hosting dengan document root public_html (PUBLIC_PATH) — berlaku untuk web & artisan.
        if (filled($publicPath = config('app.public_path'))) {
            $this->app->usePublicPath($publicPath);
        }

        // Akses Drive dihitung sekali per request (pohon folder + izin di-memo).
        $this->app->scoped(DriveAccessResolver::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Performa (Fase 10): relasi yang diakses di dalam perulangan dimuat sekaligus (mencegah N+1).
        Model::automaticallyEagerLoadRelationships();

        $this->configurePasswordRules();
        $this->configureSuperAdminGate();
        $this->configureShieldPermissionNames();
        $this->registerEventListeners();
        $this->configurePublicRateLimits();
        $this->sharePublicSiteSettings();
    }

    /**
     * Rate limit form publik (AGENTS.md B4): kirim 5/IP/jam, cek status 20/IP/jam.
     * IP hanya dipakai sebagai kunci cache sementara, tidak disimpan di database.
     */
    private function configurePublicRateLimits(): void
    {
        RateLimiter::for('public-submit', fn (Request $request) => Limit::perHour(5)->by('submit|'.$request->ip()));
        RateLimiter::for('public-status', fn (Request $request) => Limit::perHour(20)->by('status|'.$request->ip()));
    }

    /** Pengaturan website ($site) tersedia di layout & halaman publik serta halaman error. */
    private function sharePublicSiteSettings(): void
    {
        View::composer(
            ['components.layouts.public', 'public.*', 'errors::*'],
            fn ($view) => $view->with('site', Setting::allValues()),
        );
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

        $this->registerChatSync();

        // Backup gagal / tidak sehat → lonceng + push ke Super Admin (Fase 10).
        Event::listen([BackupHasFailed::class, UnhealthyBackupWasFound::class, CleanupHasFailed::class], NotifyBackupProblems::class);
    }

    /**
     * Grup chat otomatis selalu mengikuti role, divisi, organisasi, dan status aktif (Fase 9).
     * Jaring pengaman tambahan: sinkron saat membuka Chat + `chat:sync` harian.
     */
    private function registerChatSync(): void
    {
        $syncUser = function (User $user): void {
            app(ConversationSyncService::class)->syncUser($user);
        };

        Event::listen([RoleAttachedEvent::class, RoleDetachedEvent::class], function ($event) use ($syncUser): void {
            if ($event->model instanceof User) {
                $syncUser($event->model);
            }
        });

        User::saved(function (User $user) use ($syncUser): void {
            if ($user->wasRecentlyCreated || $user->wasChanged(['is_active', 'division_id', 'organization_id'])) {
                $syncUser($user);
            }
        });

        Organization::saved(function (Organization $organization): void {
            if ($organization->wasRecentlyCreated || $organization->wasChanged(['is_active', 'type', 'name'])) {
                app(ConversationSyncService::class)->syncAll();
            }
        });
    }
}
