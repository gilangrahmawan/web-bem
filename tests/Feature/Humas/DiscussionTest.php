<?php

use App\Enums\RoleName;
use App\Filament\Resources\Discussions\Pages\CreateDiscussion;
use App\Filament\Resources\Discussions\Pages\EditDiscussion;
use App\Models\Discussion;
use App\Services\DiscussionService;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

it('anggota Kajian tidak bisa mengisi docs_link walau field dikirim', function (): void {
    $anggota = member(RoleName::AnggotaKajian);

    // Lewat service (mis. request dimanipulasi): nilai dibuang.
    $data = app(DiscussionService::class)->sanitize($anggota, ['title' => 'X', 'docs_link' => 'https://docs.google.com/x']);
    expect($data['docs_link'])->toBeNull();

    // Lewat form Filament: field dinonaktifkan, dan nilai yang dipaksa tetap diabaikan.
    actingAs($anggota);
    livewire(CreateDiscussion::class)
        ->assertFormFieldIsDisabled('docs_link')
        ->set('data.docs_link', 'https://docs.google.com/document/d/curang')
        ->fillForm(['title' => 'Kajian UKT', 'status' => 'draft'])
        ->call('create')
        ->assertHasNoFormErrors();

    $discussion = Discussion::where('title', 'Kajian UKT')->sole();
    expect($discussion->docs_link)->toBeNull()->and($discussion->created_by)->toBe($anggota->id);
});

it('saat anggota mengedit, docs_link yang sudah diisi koordinator tidak berubah', function (): void {
    $anggota = member(RoleName::AnggotaKajian);
    $discussion = Discussion::factory()->create(['created_by' => $anggota->id, 'docs_link' => 'https://docs.google.com/asli']);

    $data = app(DiscussionService::class)->sanitize($anggota, ['title' => 'Baru', 'docs_link' => 'https://evil.test'], $discussion);

    expect($data['docs_link'])->toBe('https://docs.google.com/asli');
});

it('koordinator Kajian boleh mengisi docs_link (hanya http/https)', function (): void {
    $koor = member(RoleName::KoordinatorKajian);
    actingAs($koor);

    livewire(CreateDiscussion::class)
        ->fillForm(['title' => 'Kajian Fasilitas', 'status' => 'discussed', 'docs_link' => 'https://docs.google.com/document/d/abc'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Discussion::where('title', 'Kajian Fasilitas')->value('docs_link'))->toBe('https://docs.google.com/document/d/abc')
        ->and(app(DiscussionService::class)->sanitize($koor, ['docs_link' => 'javascript:alert(1)'])['docs_link'])->toBeNull();
});

it('anggota hanya mengubah/menghapus pembahasan miliknya; koordinator semua; pembina hanya melihat', function (): void {
    $anggota = member(RoleName::AnggotaKajian);
    $own = Discussion::factory()->create(['created_by' => $anggota->id]);
    $other = Discussion::factory()->create(['created_by' => member(RoleName::KoordinatorKajian)->id]);

    expect(Gate::forUser($anggota)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($anggota)->allows('update', $other))->toBeFalse()
        ->and(Gate::forUser($anggota)->allows('delete', $other))->toBeFalse()
        ->and(Gate::forUser(member(RoleName::KoordinatorKajian))->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser(member(RoleName::Pembina))->allows('viewAny', Discussion::class))->toBeTrue()
        ->and(Gate::forUser(member(RoleName::Pembina))->allows('create', Discussion::class))->toBeFalse()
        ->and(Gate::forUser(member(RoleName::AnggotaHumas))->allows('update', $own))->toBeFalse();

    actingAs($anggota)->get("/panel-bem/pembahasan/{$other->id}/edit")->assertForbidden();
    actingAs($anggota)->get("/panel-bem/pembahasan/{$own->id}/edit")->assertOk();
    actingAs(member(RoleName::Pembina))->get('/panel-bem/pembahasan')->assertOk();
    actingAs(member(RoleName::Pembina))->get('/panel-bem/pembahasan/create')->assertForbidden();
});

it('isi pembahasan disanitasi dan link referensi hanya http/https', function (): void {
    $koor = member(RoleName::KoordinatorKajian);
    $discussion = Discussion::factory()->create([
        'created_by' => $koor->id,
        'body' => '<p onclick="alert(1)">Aman</p><script>alert(1)</script>',
    ]);

    expect($discussion->fresh()->body)->not->toContain('<script')->not->toContain('onclick')->toContain('Aman');

    $data = app(DiscussionService::class)->sanitize($koor, ['reference_links' => [
        ['url' => 'https://kemdikbud.go.id', 'label' => 'Permen'],
        ['url' => 'javascript:alert(1)', 'label' => 'jahat'],
        ['url' => '', 'label' => 'kosong'],
    ]]);

    expect($data['reference_links'])->toBe([['url' => 'https://kemdikbud.go.id', 'label' => 'Permen']]);
});

it('edit oleh anggota lewat form tidak mengubah docs_link', function (): void {
    $anggota = member(RoleName::AnggotaKajian);
    $discussion = Discussion::factory()->create(['created_by' => $anggota->id, 'docs_link' => 'https://docs.google.com/asli']);
    actingAs($anggota);

    livewire(EditDiscussion::class, ['record' => $discussion->getRouteKey()])
        ->set('data.docs_link', 'https://evil.test/ganti')
        ->fillForm(['title' => 'Judul diubah'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($discussion->fresh())->title->toBe('Judul diubah')->docs_link->toBe('https://docs.google.com/asli');
});
