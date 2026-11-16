<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\MyProfile;
use App\Filament\Pages\NotificationSettings;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsurePasswordChanged;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('panel-bem')
            // Tanpa registrasi & tanpa reset password mandiri: akun hanya dibuat/direset Super Admin.
            ->login(Login::class)
            ->brandName('BEM UBHINUS')
            ->colors([
                'primary' => Color::hex('#232361'),
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarCollapsibleOnDesktop()
            // Lonceng notifikasi (channel database) + Web Push (Fase 9, lihat halaman Notifikasi).
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('BPH'),
                NavigationGroup::make('Kajian'),
                NavigationGroup::make('Medpub'),
                NavigationGroup::make('Humas'),
                NavigationGroup::make('Komunikasi'),
                NavigationGroup::make('Pengaturan'),
            ])
            ->userMenuItems([
                Action::make('my-profile')
                    ->label('Profil Saya')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => MyProfile::getUrl()),
                Action::make('notification-settings')
                    ->label('Notifikasi')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->url(fn (): string => NotificationSettings::getUrl()),
            ])
            // PWA (manifest, ikon iOS) + service worker & koneksi real-time chat (Pusher) — Fase 9.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('filament.partials.pwa-head')->render())
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('filament.partials.realtime')->render())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Pengaturan')
                    ->navigationLabel('Role & Permission')
                    ->navigationSort(20)
                    ->modelLabel('role')
                    ->pluralModelLabel('role'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent = ikut dijalankan pada request Livewire (aksi di halaman yang sudah terbuka).
            // Authenticate (app) juga mengeluarkan user yang dinonaktifkan saat sedang login.
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ], isPersistent: true);
    }
}
