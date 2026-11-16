<?php

use App\Enums\RoleName;
use App\Events\ChatMessageChanged;
use App\Filament\Pages\Chat;
use App\Models\Message;
use App\Models\Setting;
use App\Notifications\ChatMessageNotification;
use App\Services\Chat\ChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Event::fake([ChatMessageChanged::class]);
    Notification::fake();
    Storage::fake('local');
    $GLOBALS['chatTemps'] = [];
});

afterEach(function (): void {
    foreach ($GLOBALS['chatTemps'] ?? [] as $path) {
        @unlink($path);
    }
});

function cs(): ChatService
{
    return app(ChatService::class);
}

/** Berkas sungguhan (MIME dari isi). */
function chatFile(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cht');
    file_put_contents($path, $content);
    $GLOBALS['chatTemps'][] = $path;

    return new UploadedFile($path, $name, null, null, true);
}

it('isi pesan tersimpan TERENKRIPSI di database dan sinyal real-time tanpa isi pesan', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::AnggotaHumas);
    $c = cs()->startPersonal($a, $b);

    $message = cs()->send($a, $c, 'Rapat jam 7 ya');

    $raw = DB::table('messages')->where('id', $message->id)->value('body');
    expect($raw)->not->toContain('Rapat')->and(decrypt($raw, false))->toBe('Rapat jam 7 ya')
        ->and($message->fresh()->body)->toBe('Rapat jam 7 ya');

    Event::assertDispatched(ChatMessageChanged::class, function (ChatMessageChanged $e) use ($c, $a, $b): bool {
        $payload = json_encode($e->broadcastWith());
        $channels = collect($e->broadcastOn())->map->name->all();

        return $e->conversationId === $c->id && ! str_contains($payload, 'Rapat')
            && in_array('private-conversation.'.$c->id, $channels, true)
            && in_array('private-chat.user.'.$a->id, $channels, true)
            && in_array('private-chat.user.'.$b->id, $channels, true);
    });
});

it('push pesan baru ke anggota lain kecuali yang mute; tidak ke pengirim', function (): void {
    $a = member(RoleName::AnggotaMedpub);
    $b = member(RoleName::KoordinatorMedpub);
    $muted = member(RoleName::AnggotaMedpub);
    $group = cs()->createGroup($a, 'Tim Konten', [$b->id, $muted->id]);
    cs()->toggleMute($muted, $group);

    // Push hanya dikirim ke perangkat terdaftar & bila VAPID dikonfigurasi (tanpa server push sungguhan: Notification::fake).
    config(['webpush.vapid.public_key' => 'kunci-publik-uji']);
    foreach ([$a, $b, $muted] as $user) {
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/'.$user->id, 'p256dh-uji', 'auth-uji');
    }

    cs()->send($a, $group, 'Draft siap direview');

    Notification::assertSentTo($b, ChatMessageNotification::class);
    Notification::assertNotSentTo($muted, ChatMessageNotification::class);
    Notification::assertNotSentTo($a, ChatMessageNotification::class);
});

it('lampiran: gambar dikompres WebP privat; berkas diizinkan disimpan asli; jenis terlarang ditolak', function (): void {
    $a = member(RoleName::Sekretaris);
    $b = member(RoleName::Bendahara);
    $c = cs()->startPersonal($a, $b);

    $im = imagecreatetruecolor(2400, 1200);
    ob_start();
    imagejpeg($im, null, 80);
    $image = cs()->send($a, $c, null, chatFile('foto.jpg', (string) ob_get_clean()));
    expect($image->attachment_mime)->toBe('image/webp')->and($image->attachment_path)->toEndWith('.webp');
    [$w] = getimagesizefromstring(Storage::disk('local')->get($image->attachment_path));
    expect($w)->toBe(1600);

    $pdf = cs()->send($a, $c, 'Proposal', chatFile('proposal.pdf', "%PDF-1.4\n%%EOF"));
    expect($pdf->attachment_name)->toBe('proposal.pdf')->and(Storage::disk('local')->get($pdf->attachment_path))->toBe("%PDF-1.4\n%%EOF");

    expect(fn () => cs()->send($a, $c, null, chatFile('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')))->toThrow(ValidationException::class);
    expect(fn () => cs()->send($a, $c, null, chatFile('skrip.pdf', '<?php echo 1;')))->toThrow(ValidationException::class);
    expect(fn () => cs()->send($a, $c, '   '))->toThrow(ValidationException::class);
});

it('lampiran hanya bisa diunduh anggota percakapan (404 untuk lainnya, termasuk Super Admin)', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::KoordinatorKajian);
    $c = cs()->startPersonal($a, $b);
    $m = cs()->send($a, $c, null, chatFile('notulen.pdf', "%PDF-1.4\n%%EOF"));

    actingAs($b);
    get(route('panel.chat.attachment', $m))->assertOk();

    foreach ([superAdmin(), member(RoleName::AnggotaHumas), member(RoleName::PengajuKegiatan)] as $stranger) {
        actingAs($stranger);
        get(route('panel.chat.attachment', $m))->assertNotFound();
    }
});

it('hapus pesan sendiri: isi & lampiran benar-benar hilang, tampil "Pesan dihapus"; pesan orang lain tidak bisa', function (): void {
    $a = member(RoleName::AnggotaHumas);
    $b = member(RoleName::KoordinatorHumas);
    $c = cs()->startPersonal($a, $b);
    $m = cs()->send($a, $c, 'Salah kirim', chatFile('salah.pdf', "%PDF-1.4\n%%EOF"));
    $path = $m->attachment_path;

    expect(fn () => cs()->deleteMessage($b, $m->fresh()))->toThrow(AuthorizationException::class);

    cs()->deleteMessage($a, $m->fresh());

    $row = DB::table('messages')->where('id', $m->id)->first();
    expect($row->body)->toBeNull()->and($row->attachment_path)->toBeNull()->and($row->deleted_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($path);

    actingAs($b);
    livewire(Chat::class, ['conversationId' => $c->id])->assertSee('Pesan dihapus')->assertDontSee('Salah kirim');
});

it('rate limit 30 pesan per menit per user', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::AnggotaMedpub);
    $c = cs()->startPersonal($a, $b);
    RateLimiter::clear('chat-send:'.$a->id);

    foreach (range(1, 30) as $i) {
        cs()->send($a, $c, "Pesan {$i}");
    }

    expect(fn () => cs()->send($a, $c, 'Pesan 31'))->toThrow(ValidationException::class, 'Terlalu banyak pesan');
});

it('belum dibaca & tanda terbaca; membuka percakapan menandai dibaca', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::AnggotaHumas);
    $c = cs()->startPersonal($a, $b);
    $this->travel(1)->seconds();
    cs()->send($a, $c, 'Halo');
    $this->travel(1)->seconds();
    cs()->send($a, $c, 'Sudah lihat?');

    expect(cs()->unreadCount($b))->toBe(2)->and(cs()->unreadCount($a))->toBe(0);

    actingAs($a);
    livewire(Chat::class, ['conversationId' => $c->id])->assertSee('Terkirim')->assertDontSee('Dibaca');

    $this->travel(1)->seconds();
    actingAs($b);
    livewire(Chat::class, ['conversationId' => $c->id])->assertSee('Sudah lihat?');
    expect(cs()->unreadCount($b))->toBe(0);

    actingAs($a);
    livewire(Chat::class, ['conversationId' => $c->id])->assertSee('Dibaca');
});

it('kirim dari halaman Chat, balas pesan, dan cari pesan di percakapan', function (): void {
    $a = member(RoleName::Sekretaris);
    $b = member(RoleName::KoordinatorMedpub);
    $c = cs()->startPersonal($a, $b);
    $first = cs()->send($b, $c, 'Poster PKKMB sudah jadi');

    actingAs($a);
    livewire(Chat::class, ['conversationId' => $c->id])
        ->call('replyTo', $first->id)
        ->set('body', 'Mantap, kirim ke Medpub ya')
        ->call('send')
        ->assertSet('body', '')
        ->assertSee('Mantap, kirim ke Medpub ya')
        ->set('messageSearch', 'poster')
        ->assertSee('Poster PKKMB sudah jadi')->assertSee('Hasil pencarian di 200 pesan terakhir: 1 pesan');

    expect(Message::where('conversation_id', $c->id)->latest('id')->first()->reply_to_id)->toBe($first->id);
});

it('polling 5 detik bila Pusher tidak tersedia; dimatikan saat real-time tersambung', function (): void {
    actingAs(member(RoleName::AnggotaKajian));

    livewire(Chat::class)->assertSet('polling', true)->assertSeeHtml('wire:poll.5s')
        ->dispatch('chat-realtime', connected: true)->assertSet('polling', false)->assertDontSeeHtml('wire:poll.5s')
        ->dispatch('chat-realtime', connected: false)->assertSet('polling', true);
});

it('lampiran lebih dari 12 bulan dihapus otomatis; teks tetap, tampil "Lampiran sudah kedaluwarsa"', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::KoordinatorKajian);
    $c = cs()->startPersonal($a, $b);

    $this->travelTo(now()->subMonths(13));
    $old = cs()->send($a, $c, 'Arsip lama', chatFile('lama.pdf', "%PDF-1.4\n%%EOF"));
    $this->travelBack();
    $new = cs()->send($a, $c, 'Baru', chatFile('baru.pdf', "%PDF-1.4\n%%EOF"));
    $oldPath = $old->attachment_path;

    $this->artisan('chat:maintenance')->expectsOutputToContain('Lampiran kedaluwarsa dihapus: 1')->assertSuccessful();

    $old->refresh();
    expect($old->attachment_path)->toBeNull()->and($old->attachment_expired_at)->not->toBeNull()->and($old->body)->toBe('Arsip lama')
        ->and($new->fresh()->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertMissing($oldPath);

    actingAs($b);
    livewire(Chat::class, ['conversationId' => $c->id])->assertSee('Lampiran sudah kedaluwarsa')->assertSee('Arsip lama');

    Setting::set('chat_attachment_retention_months', 1);
    expect(ChatService::retentionMonths())->toBe(1);
});
