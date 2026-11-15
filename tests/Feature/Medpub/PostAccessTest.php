<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\RoleName;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Policies\PostPolicy;
use App\Services\PostContentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('tipe konten yang boleh dibuat mengikuti permission', function (RoleName $role, array $types): void {
    expect(PostPolicy::allowedTypes(member($role)))->toBe($types);
})->with([
    'anggota medpub' => [RoleName::AnggotaMedpub, [PostType::Article, PostType::Social]],
    'koordinator medpub' => [RoleName::KoordinatorMedpub, [PostType::Article, PostType::Social, PostType::PressRelease]],
    'anggota humas' => [RoleName::AnggotaHumas, [PostType::PressRelease]],
    'koordinator humas' => [RoleName::KoordinatorHumas, [PostType::PressRelease]],
    'anggota kajian' => [RoleName::AnggotaKajian, [PostType::Study]],
    'koordinator kajian' => [RoleName::KoordinatorKajian, [PostType::Study]],
    'sekretaris' => [RoleName::Sekretaris, []],
    'pembina' => [RoleName::Pembina, []],
]);

it('Humas hanya bisa membuat press release, Kajian hanya kajian publik', function (): void {
    $humas = member(RoleName::AnggotaHumas);
    $kajian = member(RoleName::AnggotaKajian);
    $service = app(PostContentService::class);

    expect(fn () => $service->createDraft($humas, ['type' => 'article', 'title' => 'Artikel']))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->createDraft($kajian, ['type' => 'press_release', 'title' => 'PR']))->toThrow(AuthorizationException::class);

    expect($service->createDraft($humas, ['type' => 'press_release', 'title' => 'Sikap BEM'])->type)->toBe(PostType::PressRelease)
        ->and($service->createDraft($kajian, ['type' => 'study', 'title' => 'Kajian UKT'])->type)->toBe(PostType::Study);
});

it('form Konten hanya menawarkan tipe yang diizinkan dan draft milik penulis', function (): void {
    $humas = member(RoleName::KoordinatorHumas);
    actingAs($humas);

    livewire(CreatePost::class)
        ->fillForm(['type' => 'press_release', 'title' => 'Pernyataan Sikap BEM'])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::where('title', 'Pernyataan Sikap BEM')->sole();
    expect($post->author_id)->toBe($humas->id)->and($post->status)->toBe(PostStatus::Draft);

    livewire(CreatePost::class)
        ->fillForm(['type' => 'article', 'title' => 'Artikel Nyusup'])
        ->call('create')
        ->assertHasFormErrors(['type']);

    expect(Post::where('title', 'Artikel Nyusup')->exists())->toBeFalse();
});

it('draft & revisi orang lain tidak terlihat; konten diajukan terlihat semua pengurus (read-only)', function (): void {
    $penulis = member(RoleName::AnggotaMedpub);
    $draft = Post::factory()->create(['author_id' => $penulis->id, 'status' => PostStatus::Draft]);
    $revisi = Post::factory()->create(['author_id' => $penulis->id, 'status' => PostStatus::Revision]);
    $pending = Post::factory()->create(['author_id' => $penulis->id, 'status' => PostStatus::PendingLeader]);

    foreach ([RoleName::AnggotaMedpub, RoleName::Pembina, RoleName::Sekretaris, RoleName::AnggotaHumas] as $role) {
        $user = member($role);
        $visible = Post::query()->visibleTo($user)->pluck('id');

        expect($visible)->toContain($pending->id)->not->toContain($draft->id)->not->toContain($revisi->id)
            ->and(Gate::forUser($user)->allows('view', $draft))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $pending))->toBeFalse();
    }

    foreach ([member(RoleName::KoordinatorMedpub), superAdmin(), $penulis] as $user) {
        expect(Post::query()->visibleTo($user)->pluck('id'))->toContain($draft->id);
    }

    actingAs(member(RoleName::Pembina))->get("/panel-bem/konten/{$draft->id}")->assertNotFound();
    actingAs(member(RoleName::Pembina))->get("/panel-bem/konten/{$pending->id}")->assertOk();
});

it('akses halaman Konten: semua pengurus melihat, hanya pembuat konten bisa membuat', function (): void {
    $creators = [RoleName::SuperAdmin, RoleName::KoordinatorMedpub, RoleName::AnggotaMedpub, RoleName::KoordinatorHumas,
        RoleName::AnggotaHumas, RoleName::KoordinatorKajian, RoleName::AnggotaKajian];

    foreach (RoleName::cases() as $role) {
        $user = member($role);
        actingAs($user)->get('/panel-bem/konten')->assertOk();
        actingAs($user)->get('/panel-bem/konten/create')->assertStatus(in_array($role, $creators, true) ? 200 : 403);
    }
});

it('super_admin pun tidak bisa mengedit draft milik orang lain', function (): void {
    $draft = Post::factory()->create(['author_id' => member(RoleName::AnggotaMedpub)->id, 'status' => PostStatus::Draft]);

    expect(Gate::forUser(superAdmin())->allows('update', $draft))->toBeFalse()
        ->and(Gate::forUser(superAdmin())->allows('submit', $draft))->toBeFalse();
});

it('halaman publik hanya menampilkan konten terbit', function (): void {
    $author = member(RoleName::AnggotaMedpub);
    $published = Post::factory()->published()->create(['author_id' => $author->id, 'title' => 'Berita Sudah Terbit']);
    Post::factory()->create(['author_id' => $author->id, 'title' => 'Masih Menunggu Ketua', 'status' => PostStatus::PendingLeader]);
    Post::factory()->create(['author_id' => $author->id, 'title' => 'Sudah Disetujui Belum Terbit', 'status' => PostStatus::Approved]);

    $this->get('/berita')->assertOk()->assertSee('Berita Sudah Terbit')
        ->assertDontSee('Masih Menunggu Ketua')->assertDontSee('Sudah Disetujui Belum Terbit');
});

it('permission post.create_study ditambahkan tanpa menghapus permission lain', function (): void {
    $role = Role::findByName('anggota_kajian', 'web');
    $role->givePermissionTo('kas.manage'); // perubahan manual lewat Shield
    $before = $role->permissions()->pluck('name')->all();

    $migration = require database_path('migrations/2026_11_21_100000_add_post_create_study_permission.php');
    $migration->up();

    $after = $role->fresh()->permissions()->pluck('name')->all();

    expect($after)->toContain('post.create_study')->toContain('kas.manage')
        ->and(array_diff($before, $after))->toBe([])
        ->and(Role::findByName('koordinator_kajian', 'web')->hasPermissionTo('post.create_study'))->toBeTrue();
});
