<?php

namespace App\Notifications\Concerns;

use App\Enums\NotificationCategory;
use App\Models\User;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push notification (Fase 9) untuk semua notifikasi:
 * - Lonceng (database) SELALU dikirim langsung (koneksi sync).
 * - Web Push hanya bila user aktif, punya perangkat terdaftar, kunci VAPID ada, dan jenis ini tidak
 *   dimatikan di preferensinya. Dikirim lewat antrean `database` (diproses scheduler tiap menit),
 *   sehingga halaman tidak menunggu server push.
 * - Isi push (judul, isi, URL) diambil dari pesan lonceng agar teksnya selalu sama.
 *
 * Kelas pemakai wajib `implements ShouldQueue` + `use Queueable`.
 */
trait SendsWebPush
{
    abstract public function pushCategory(): NotificationCategory;

    /**
     * @param  list<string>  $base
     * @return list<string>
     */
    protected function channelsFor(User $notifiable, array $base = ['database']): array
    {
        if ($this->shouldPush($notifiable)) {
            $base[] = WebPushChannel::class;
        }

        return $base;
    }

    protected function shouldPush(User $notifiable): bool
    {
        return $notifiable->is_active
            && filled(config('webpush.vapid.public_key'))
            && $notifiable->wantsPush($this->pushCategory())
            && $notifiable->pushSubscriptions()->exists();
    }

    /** @return array<string, string|null> */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            WebPushChannel::class => config('queue.default'),
        ];
    }

    public function toWebPush(User $notifiable, mixed $notification): WebPushMessage
    {
        $payload = method_exists($this, 'pushPayload') ? $this->pushPayload($notifiable) : $this->toDatabase($notifiable);

        $url = collect($payload['actions'] ?? [])->pluck('url')->filter()->first()
            ?? ($payload['url'] ?? url('/panel-bem'));

        return (new WebPushMessage)
            ->title(Str::limit(trim(strip_tags((string) ($payload['title'] ?? 'BEM UBHINUS'))), 80, '…'))
            ->body(Str::limit(trim(strip_tags((string) ($payload['body'] ?? ''))), 180, '…'))
            ->icon('/images/icon-192.png')
            ->badge('/images/icon-maskable-192.png')
            ->tag($payload['tag'] ?? $this->pushCategory()->value)
            ->data(['url' => $url])
            ->options(['TTL' => 86400, 'urgency' => 'normal']);
    }
}
