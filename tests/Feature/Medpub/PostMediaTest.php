<?php

use App\Enums\EventCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Filament\Pages\MyTasks;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Event;
use App\Models\EventAssignee;
use App\Models\Post;
use App\Services\PostContentService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('upload cover menyimpan WebP untuk website dan file ASLI di disk privat', function (): void {
    $anggota = member(RoleName::AnggotaMedpub);
    actingAs($anggota);

    livewire(CreatePost::class)
        ->fillForm([
            'type' => 'social',
            'title' => 'Poster Hari Guru',
            'cover' => UploadedFile::fake()->image('poster-asli.png', 2400, 2400),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::where('title', 'Poster Hari Guru')->sole();

    expect($post->cover)->toStartWith('posts/')->toEndWith('.webp')
        ->and($post->cover_original_path)->toStartWith('post-originals/')->toEndWith('.png')
        ->and($post->cover_original_name)->toBe('poster-asli.png')
        ->and(pathinfo($post->cover, PATHINFO_FILENAME))->toBe(pathinfo($post->cover_original_path, PATHINFO_FILENAME));

    Storage::disk('public')->assertExists($post->cover);
    Storage::disk('local')->assertExists($post->cover_original_path);
    Storage::disk('public')->assertMissing($post->cover_original_path); // asli tidak pernah di disk public
});

it('"Unduh gambar" memberikan file asli, hanya untuk yang berhak melihat konten', function (): void {
    Storage::disk('public')->put('posts/abc.webp', 'webp');
    Storage::disk('local')->put('post-originals/abc.png', 'png-asli');
    $penulis = member(RoleName::AnggotaMedpub);
    $post = Post::factory()->type(PostType::Social)->create([
        'author_id' => $penulis->id,
        'title' => 'Poster Kemerdekaan',
        'status' => PostStatus::Draft,
        'cover' => 'posts/abc.webp',
        'cover_original_path' => 'post-originals/abc.png',
    ]);
    $url = route('panel.posts.image', $post);

    actingAs($penulis)->get($url)->assertOk()->assertDownload('poster-kemerdekaan.png');
    actingAs(member(RoleName::KoordinatorMedpub))->get($url)->assertOk();
    actingAs(member(RoleName::AnggotaHumas))->get($url)->assertNotFound(); // draft orang lain

    $post->forceFill(['status' => PostStatus::PendingCoordinator])->save();
    actingAs(member(RoleName::AnggotaHumas))->get($url)->assertOk();
});

it('path cover/asli dari browser tidak dipercaya', function (): void {
    Storage::disk('local')->put('task-results/rahasia.pdf', 'rahasia');
    $anggota = member(RoleName::AnggotaMedpub);

    $post = app(PostContentService::class)->createDraft($anggota, [
        'type' => 'social',
        'title' => 'Coba curang',
        'cover' => 'task-results/rahasia.pdf',
        'cover_original_path' => 'task-results/rahasia.pdf',
    ]);

    expect($post->cover)->toBeNull()->and($post->cover_original_path)->toBeNull();
});

function submittedTask(?string $resultPath, ?string $originalName = null): EventAssignee
{
    $event = Event::factory()->category(EventCategory::Content)->create(['title' => 'Poster Hari Pahlawan', 'division_id' => divisionId('medpub')]);

    return EventAssignee::factory()->create([
        'event_id' => $event->id,
        'user_id' => member(RoleName::AnggotaMedpub)->id,
        'status' => TaskStatus::Submitted,
        'result_path' => $resultPath,
        'result_original_name' => $originalName,
        'result_url' => 'https://www.canva.com/design/abc',
    ]);
}

it('draft dari tugas: hasil gambar jadi cover WebP + file asli, tertaut ke tugas', function (): void {
    $image = UploadedFile::fake()->image('x.png', 1200, 1200);
    Storage::disk('local')->put('task-results/hasil.png', file_get_contents($image->getRealPath()));
    $task = submittedTask('task-results/hasil.png', 'Poster Final.png');
    $koor = member(RoleName::KoordinatorMedpub);
    actingAs($koor);

    livewire(MyTasks::class)
        ->filterTable('mine', false)
        ->callAction(TestAction::make('draftPost')->table($task));

    $post = Post::where('event_assignee_id', $task->id)->sole();

    expect($post->author_id)->toBe($koor->id)
        ->and($post->type)->toBe(PostType::Social)
        ->and($post->title)->toBe('Poster Hari Pahlawan')
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->cover_original_name)->toBe('Poster Final.png');

    Storage::disk('public')->assertExists($post->cover);
    Storage::disk('local')->assertExists($post->cover_original_path);
    Storage::disk('local')->assertExists('task-results/hasil.png'); // berkas tugas tidak dipindah
});

it('draft dari tugas dengan hasil non-gambar tidak membuat cover; hanya tugas Diserahkan & oleh koordinator', function (): void {
    Storage::disk('local')->put('task-results/hasil.pdf', '%PDF');
    $task = submittedTask('task-results/hasil.pdf');
    $koor = member(RoleName::KoordinatorMedpub);

    $post = app(PostContentService::class)->draftFromTask($koor, $task);
    expect($post->cover)->toBeNull()->and($post->cover_original_path)->toBeNull();

    $todo = submittedTask(null);
    $todo->update(['status' => TaskStatus::InProgress]);

    expect(fn () => app(PostContentService::class)->draftFromTask($koor, $todo))->toThrow(ValidationException::class)
        ->and(fn () => app(PostContentService::class)->draftFromTask(member(RoleName::AnggotaMedpub), submittedTask(null)))
        ->toThrow(AuthorizationException::class);
});
