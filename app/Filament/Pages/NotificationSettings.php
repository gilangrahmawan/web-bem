<?php

namespace App\Filament\Pages;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\TestPushNotification;
use App\Support\Format;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\PushSubscription;
use Throwable;

/**
 * Notifikasi (Fase 9): aktifkan Web Push di perangkat ini, daftar perangkat, preferensi push per jenis
 * (lonceng di dashboard SELALU masuk), kirim notifikasi uji, dan panduan iPhone.
 * Semua pengguna panel (termasuk pengaju & Pembina) — hanya data miliknya sendiri.
 */
class NotificationSettings extends Page
{
    protected static ?string $slug = 'notifikasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pengaturan Notifikasi';

    protected string $view = 'filament.pages.notification-settings';

    /** @var array<string, bool> */
    public array $preferences = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active;
    }

    public function mount(): void
    {
        $user = $this->user();

        foreach (NotificationCategory::configurable() as $category) {
            $this->preferences[$category->value] = $user->wantsPush($category);
        }
    }

    public function updatedPreferences(): void
    {
        $clean = [];

        foreach (NotificationCategory::configurable() as $category) {
            $clean[$category->value] = (bool) ($this->preferences[$category->value] ?? true);
        }

        $this->user()->forceFill(['notification_preferences' => $clean])->save();
        $this->preferences = $clean;

        Notification::make()->title('Preferensi tersimpan')->success()->send();
    }

    public function removeDevice(int $id): void
    {
        $this->user()->pushSubscriptions()->whereKey($id)->delete();
        Notification::make()->title('Perangkat dihapus dari notifikasi')->success()->send();
    }

    public function sendTest(): void
    {
        $user = $this->user();

        if ($user->pushSubscriptions()->doesntExist()) {
            Notification::make()->title('Belum ada perangkat terdaftar')->body('Tekan "Aktifkan di perangkat ini" terlebih dahulu.')->warning()->send();

            return;
        }

        try {
            $user->notifyNow(new TestPushNotification);
            Notification::make()->title('Notifikasi uji dikirim')->body('Bila tidak muncul dalam beberapa detik, periksa izin notifikasi di browser/HP.')->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Gagal mengirim notifikasi uji')
                ->body(Str::contains($e->getMessage(), ['key', 'OpenSSL', 'openssl'])
                    ? 'Server belum bisa membuat kunci enkripsi push (OpenSSL). Lihat docs/CATATAN-PRODUKSI.md.'
                    : 'Coba lagi beberapa saat lagi.')
                ->danger()->persistent()->send();
        }
    }

    protected function getViewData(): array
    {
        $user = $this->user();

        return [
            'categories' => NotificationCategory::configurable(),
            'vapidPublicKey' => (string) config('webpush.vapid.public_key'),
            'devices' => $user->pushSubscriptions()->latest('id')->get()->map(fn (PushSubscription $s) => [
                'id' => $s->getKey(),
                'endpoint' => $s->endpoint,
                'service' => match (true) {
                    Str::contains($s->endpoint, 'fcm.googleapis.com') => 'Chrome / Android',
                    Str::contains($s->endpoint, 'push.apple.com') => 'Safari / iPhone',
                    Str::contains($s->endpoint, 'mozilla') => 'Firefox',
                    Str::contains($s->endpoint, ['windows.com', 'notify.windows']) => 'Edge / Windows',
                    default => 'Browser lain',
                },
                'since' => Format::date($s->created_at),
            ]),
        ];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
