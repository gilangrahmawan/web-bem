<?php

use App\Enums\AnnouncementType;
use App\Enums\RoleName;
use App\Filament\Pages\Inbox;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\RecipientsRelationManager;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Notifications\AnnouncementReceivedNotification;
use App\Services\AnnouncementService;
use App\Services\Letters\LetterArchiver;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

function sendMessage(AnnouncementType $type, array $data, $actor = null): Announcement
{
    $actor ??= member($type === AnnouncementType::Letter ? RoleName::KoordinatorHumas : RoleName::Sekretaris);

    return app(AnnouncementService::class)->send($actor, $type, ['title' => 'Rapat Pleno', 'body' => 'Hadir pukul 19.00.', ...$data]);
}

it('pengumuman hanya sampai ke target, tidak ke user nonaktif maupun pengirim', function (): void {
    Notification::fake();
    $sekretaris = member(RoleName::Sekretaris);
    $kajian1 = member(RoleName::AnggotaKajian);
    $kajian2 = member(RoleName::KoordinatorKajian);
    $kajianNonaktif = member(RoleName::AnggotaKajian, ['is_active' => false]);
    $humas = member(RoleName::AnggotaHumas);
    $bendahara = member(RoleName::Bendahara);

    $announcement = sendMessage(AnnouncementType::Announcement, [
        'target_all' => false,
        'divisions' => [divisionId('kajian')],
        'users' => [$bendahara->id],
    ], $sekretaris);

    $recipients = $announcement->recipients()->pluck('user_id')->sort()->values()->all();

    expect($recipients)->toBe(collect([$kajian1->id, $kajian2->id, $bendahara->id])->sort()->values()->all())
        ->and($recipients)->not->toContain($kajianNonaktif->id)
        ->and($recipients)->not->toContain($humas->id)
        ->and($recipients)->not->toContain($sekretaris->id);

    Notification::assertSentTo([$kajian1, $kajian2, $bendahara], AnnouncementReceivedNotification::class);
    Notification::assertNotSentTo([$kajianNonaktif, $humas, $sekretaris], AnnouncementReceivedNotification::class);
    $this->assertDatabaseHas('announcement_targets', ['announcement_id' => $announcement->id, 'target_type' => 'division']);
});

it('target "semua pengurus" menyertakan Pembina, tanpa user nonaktif', function (): void {
    $pembina = member(RoleName::Pembina);
    $nonaktif = member(RoleName::AnggotaMedpub, ['is_active' => false]);

    $recipients = sendMessage(AnnouncementType::Announcement, ['target_all' => true])->recipients()->pluck('user_id');

    expect($recipients)->toContain($pembina->id)->not->toContain($nonaktif->id);
});

it('target per role sampai ke pemegang role tersebut', function (): void {
    $koorHumas = member(RoleName::KoordinatorHumas);
    $koorMedpub = member(RoleName::KoordinatorMedpub);
    member(RoleName::AnggotaHumas);

    $recipients = sendMessage(AnnouncementType::Announcement, ['target_all' => false, 'roles' => ['koordinator_humas', 'koordinator_medpub']])
        ->recipients()->pluck('user_id')->sort()->values()->all();

    expect($recipients)->toBe(collect([$koorHumas->id, $koorMedpub->id])->sort()->values()->all());
});

it('hanya Sekretaris mengirim pengumuman dan Humas mengirim surat; target kosong ditolak', function (): void {
    expect(fn () => sendMessage(AnnouncementType::Announcement, ['target_all' => true], member(RoleName::AnggotaHumas)))->toThrow(AuthorizationException::class)
        ->and(fn () => sendMessage(AnnouncementType::Letter, ['target_all' => true], member(RoleName::Sekretaris)))->toThrow(AuthorizationException::class)
        ->and(fn () => sendMessage(AnnouncementType::Announcement, ['target_all' => false]))->toThrow(ValidationException::class);

    member(RoleName::Bendahara);
    expect(sendMessage(AnnouncementType::Letter, ['target_all' => true], member(RoleName::AnggotaHumas))->type)->toBe(AnnouncementType::Letter);
});

it('surat memanggil arsip Drive (hook Fase 8), pengumuman tidak', function (): void {
    member(RoleName::Bendahara);
    $archiver = Mockery::mock(LetterArchiver::class);
    $archiver->shouldReceive('archive')->once();
    app()->instance(LetterArchiver::class, $archiver);

    sendMessage(AnnouncementType::Letter, ['target_all' => true]);
    sendMessage(AnnouncementType::Announcement, ['target_all' => true]);
});

it('tidak bisa diedit setelah terkirim (termasuk super_admin); hapus hanya super_admin', function (): void {
    member(RoleName::Bendahara);
    $announcement = sendMessage(AnnouncementType::Announcement, ['target_all' => true]);

    expect(Gate::forUser(superAdmin())->allows('update', $announcement))->toBeFalse()
        ->and(Gate::forUser($announcement->creator)->allows('update', $announcement))->toBeFalse()
        ->and(Gate::forUser($announcement->creator)->allows('delete', $announcement))->toBeFalse()
        ->and(Gate::forUser(superAdmin())->allows('delete', $announcement))->toBeTrue();

    actingAs($announcement->creator)->get("/panel-bem/pengumuman/{$announcement->id}/edit")->assertNotFound();
});

it('Kotak Masuk otomatis menandai dibaca saat dibuka, dan hanya menampilkan pesan milik sendiri', function (): void {
    $saya = member(RoleName::AnggotaMedpub);
    $lain = member(RoleName::AnggotaHumas);
    sendMessage(AnnouncementType::Announcement, ['target_all' => false, 'users' => [$saya->id], 'title' => 'Untuk Saya']);
    sendMessage(AnnouncementType::Announcement, ['target_all' => false, 'users' => [$lain->id], 'title' => 'Untuk Orang Lain']);
    $mine = AnnouncementRecipient::where('user_id', $saya->id)->sole();

    actingAs($saya);
    livewire(Inbox::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords(AnnouncementRecipient::where('user_id', $lain->id)->get())
        ->mountAction(TestAction::make('open')->table($mine));

    expect($mine->fresh()->read_at)->not->toBeNull()
        ->and(Inbox::getNavigationBadge())->toBeNull();
});

it('penerima surat bisa membalas dan menandai ditindaklanjuti; user lain tidak bisa', function (): void {
    $penerima = member(RoleName::KoordinatorKajian);
    $letter = sendMessage(AnnouncementType::Letter, ['target_all' => false, 'users' => [$penerima->id]]);
    $row = $letter->recipients()->sole();

    actingAs($penerima);
    livewire(Inbox::class)
        ->callAction(TestAction::make('reply')->table($row), data: ['reply' => 'Siap, kami hadir.'])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('followUp')->table($row));

    expect($row->fresh())
        ->reply->toBe('Siap, kami hadir.')
        ->followed_up_at->not->toBeNull()
        ->read_at->not->toBeNull();

    expect(fn () => app(AnnouncementService::class)->reply(member(RoleName::AnggotaHumas), $row, 'nyusup'))->toThrow(AuthorizationException::class);
});

it('pengumuman tidak bisa dibalas', function (): void {
    $penerima = member(RoleName::AnggotaKajian);
    $row = sendMessage(AnnouncementType::Announcement, ['target_all' => false, 'users' => [$penerima->id]])->recipients()->sole();

    expect(fn () => app(AnnouncementService::class)->reply($penerima, $row, 'halo'))->toThrow(ValidationException::class);
});

it('lampiran hanya bisa diunduh penerima, pengirim, pengelola, dan pembina', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('announcements/undangan.pdf', '%PDF-1.4 uji');
    $penerima = member(RoleName::AnggotaKajian);
    $announcement = sendMessage(AnnouncementType::Announcement, [
        'target_all' => false,
        'users' => [$penerima->id],
        'attachment_path' => 'announcements/undangan.pdf',
        'attachment_name' => 'Undangan Rapat.pdf',
    ]);
    $url = route('panel.announcements.attachment', $announcement);

    actingAs($penerima)->get($url)->assertOk()->assertDownload('Undangan Rapat.pdf');
    actingAs($announcement->creator)->get($url)->assertOk();
    actingAs(member(RoleName::Pembina))->get($url)->assertOk();
    actingAs(member(RoleName::AnggotaHumas))->get($url)->assertNotFound();
    actingAs(member(RoleName::Bendahara))->get($url)->assertNotFound();
});

it('Sekretaris mengirim pengumuman dari dashboard dan melihat daftar pembaca', function (): void {
    $penerima = member(RoleName::AnggotaMedpub);
    $sekretaris = member(RoleName::Sekretaris);
    actingAs($sekretaris);

    livewire(CreateAnnouncement::class)
        ->fillForm(['title' => 'Libur Sekre', 'body' => 'Sekretariat tutup Jumat.', 'target_all' => false, 'users' => [$penerima->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $announcement = Announcement::where('title', 'Libur Sekre')->sole();
    expect($announcement->recipients()->pluck('user_id')->all())->toBe([$penerima->id]);

    // Daftar penerima (relation manager) dimuat lazy; ringkasan baca ada di halaman, daftar diuji langsung.
    actingAs($sekretaris)->get("/panel-bem/pengumuman/{$announcement->id}")->assertOk()->assertSee('0/1 penerima');
    livewire(RecipientsRelationManager::class, [
        'ownerRecord' => $announcement,
        'pageClass' => ViewAnnouncement::class,
    ])->assertSee($penerima->name);
});

it('akses resource Pengumuman & Surat sesuai izin', function (): void {
    foreach (RoleName::cases() as $role) {
        $user = member($role);
        $announcementOk = in_array($role, [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::Pembina], true);
        $letterOk = in_array($role, [RoleName::SuperAdmin, RoleName::KoordinatorHumas, RoleName::AnggotaHumas, RoleName::Pembina], true);

        actingAs($user)->get('/panel-bem/pengumuman')->assertStatus($announcementOk ? 200 : 403);
        actingAs($user)->get('/panel-bem/surat')->assertStatus($letterOk ? 200 : 403);
        actingAs($user)->get('/panel-bem/kotak-masuk')->assertOk();
    }

    actingAs(member(RoleName::Pembina))->get('/panel-bem/pengumuman/create')->assertForbidden();
    // Regresi: halaman Create hanya untuk pemegang izin tipe itu (Humas tidak bisa membuka Create Pengumuman, Sekretaris tidak bisa Create Surat).
    actingAs(member(RoleName::KoordinatorHumas))->get('/panel-bem/pengumuman/create')->assertForbidden();
    actingAs(member(RoleName::Sekretaris))->get('/panel-bem/surat/create')->assertForbidden();
    actingAs(member(RoleName::Sekretaris))->get('/panel-bem/pengumuman/create')->assertOk();
    actingAs(member(RoleName::AnggotaHumas))->get('/panel-bem/surat/create')->assertOk();
});
