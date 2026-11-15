<?php

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStage;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\RoleName;
use App\Models\Post;
use App\Notifications\PostWorkflowNotification;
use App\Services\PostContentService;
use App\Services\PostWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function workflow(): PostWorkflowService
{
    return app(PostWorkflowService::class);
}

function draftBy($author, PostType $type = PostType::Article, array $attributes = []): Post
{
    return Post::factory()->type($type)->create([
        'author_id' => $author->id,
        'status' => PostStatus::Draft,
        'cover' => 'posts/contoh.webp',
        'social_caption' => 'Caption contoh #BEMUBHINUS',
        ...$attributes,
    ]);
}

/** Tim lengkap: koordinator medpub, dua super_admin (Ketua & Wakil). */
function contentTeam(): array
{
    return [
        'koor' => member(RoleName::KoordinatorMedpub),
        'ketua' => superAdmin(['name' => 'Ketua']),
        'wakil' => superAdmin(['name' => 'Wakil']),
    ];
}

it('alur lengkap: anggota → Koordinator → Ketua (cukup satu) → terbit di website', function (): void {
    Notification::fake();
    ['koor' => $koor, 'ketua' => $ketua, 'wakil' => $wakil] = contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);

    workflow()->submit($anggota, $post);
    expect($post->fresh()->status)->toBe(PostStatus::PendingCoordinator);
    Notification::assertSentTo($koor, PostWorkflowNotification::class);

    workflow()->approveByCoordinator($koor, $post->fresh());
    expect($post->fresh()->status)->toBe(PostStatus::PendingLeader);
    Notification::assertSentTo([$ketua, $wakil], PostWorkflowNotification::class);

    workflow()->approveByLeader($ketua, $post->fresh()); // Wakil tidak perlu ikut menyetujui
    expect($post->fresh()->status)->toBe(PostStatus::Approved);

    workflow()->publish($koor, $post->fresh());
    $post->refresh();

    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_by)->toBe($koor->id)
        ->and($post->isPublished())->toBeTrue()
        ->and($post->approvals->map(fn ($a) => [$a->stage->value, $a->decision->value])->all())
        ->toBe([['coordinator', 'approved'], ['leader', 'approved']]);

    $this->get($post->publicUrl())->assertOk()->assertSee($post->title);
    $this->assertDatabaseHas('activity_logs', ['action' => 'post.published', 'subject_id' => $post->id]);
});

it('anggota tidak bisa menerbitkan', function (): void {
    contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);
    $post->forceFill(['status' => PostStatus::Approved])->save();

    expect(Gate::forUser($anggota)->allows('publish', $post))->toBeFalse()
        ->and(fn () => workflow()->publish($anggota, $post))->toThrow(AuthorizationException::class)
        ->and($post->fresh()->status)->toBe(PostStatus::Approved);
});

it('tidak bisa terbit tanpa persetujuan Koordinator DAN Ketua/Wakil, walau status dimanipulasi', function (): void {
    ['koor' => $koor] = contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);

    // Tanpa persetujuan sama sekali.
    $post = draftBy($anggota);
    $post->forceFill(['status' => PostStatus::Approved, 'review_round' => 1])->save();
    expect(fn () => workflow()->publish($koor, $post))->toThrow(ValidationException::class);

    // Hanya disetujui koordinator.
    $post2 = draftBy($anggota);
    workflow()->submit($anggota, $post2);
    workflow()->approveByCoordinator($koor, $post2->fresh());
    $post2->forceFill(['status' => PostStatus::Approved])->save();
    expect(fn () => workflow()->publish($koor, $post2->fresh()))->toThrow(ValidationException::class);

    expect(Post::where('status', PostStatus::Published->value)->count())->toBe(0);
});

it('penulis tidak bisa mengedit saat pending', function (): void {
    contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);
    workflow()->submit($anggota, $post);
    $post->refresh();

    expect(Gate::forUser($anggota)->allows('update', $post))->toBeFalse()
        ->and(fn () => app(PostContentService::class)->updateDraft($anggota, $post, ['type' => 'article', 'title' => 'Diam-diam diubah']))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($anggota)->get("/panel-bem/konten/{$post->id}/edit")->assertForbidden();
    expect($post->fresh()->title)->not->toBe('Diam-diam diubah');
});

it('revisi mengembalikan ke penulis dan kedua tahap wajib disetujui ulang', function (): void {
    Notification::fake();
    ['koor' => $koor, 'ketua' => $ketua] = contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);

    workflow()->submit($anggota, $post);
    workflow()->approveByCoordinator($koor, $post->fresh());
    workflow()->requestRevision($ketua, $post->fresh(), 'Judul kurang jelas, mohon diperbaiki.');

    expect($post->fresh()->status)->toBe(PostStatus::Revision)
        ->and(Gate::forUser($anggota)->allows('update', $post->fresh()))->toBeTrue();
    Notification::assertSentTo($anggota, PostWorkflowNotification::class, fn ($n) => str_contains($n->body, 'Judul kurang jelas'));

    app(PostContentService::class)->updateDraft($anggota, $post->fresh(), ['type' => 'article', 'title' => 'Judul yang lebih jelas', 'body' => '<p>Isi</p>']);
    workflow()->submit($anggota, $post->fresh());
    $post->refresh();

    expect($post->status)->toBe(PostStatus::PendingCoordinator)->and($post->review_round)->toBe(2);

    // Persetujuan putaran 1 tidak berlaku lagi.
    $post->forceFill(['status' => PostStatus::Approved])->save();
    expect(fn () => workflow()->publish($koor, $post->fresh()))->toThrow(ValidationException::class);
});

it('konten Koordinator Medpub melewati tahap koordinator (tercatat) dan tidak bisa ia setujui sendiri', function (): void {
    ['koor' => $koor, 'ketua' => $ketua] = contentTeam();
    $post = draftBy($koor);

    workflow()->submit($koor, $post);
    $post->refresh();

    expect($post->status)->toBe(PostStatus::PendingLeader)
        ->and($post->approvals->first())->stage->toBe(ApprovalStage::Coordinator)
        ->and($post->approvals->first()->decision)->toBe(ApprovalDecision::AutoApproved);

    workflow()->approveByLeader($ketua, $post);
    workflow()->publish($koor, $post->fresh());

    expect($post->fresh()->status)->toBe(PostStatus::Published);

    $other = draftBy(member(RoleName::AnggotaMedpub));
    workflow()->submit($other->author, $other);
    expect(Gate::forUser($ketua)->allows('approveCoordinator', $other->fresh()))->toBeFalse(); // super_admin bukan penyetuju tahap koordinator
});

it('konten Ketua harus disetujui Wakil; Ketua tidak bisa menyetujui kontennya sendiri', function (): void {
    ['koor' => $koor, 'ketua' => $ketua, 'wakil' => $wakil] = contentTeam();
    $post = draftBy($ketua);

    workflow()->submit($ketua, $post);
    workflow()->approveByCoordinator($koor, $post->fresh());

    expect(fn () => workflow()->approveByLeader($ketua, $post->fresh()))
        ->toThrow(AuthorizationException::class, 'tidak boleh menyetujui konten Anda sendiri');

    workflow()->approveByLeader($wakil, $post->fresh());
    expect($post->fresh()->status)->toBe(PostStatus::Approved);
});

it('pesan jelas bila tidak ada penyetuju aktif lain', function (): void {
    $ketua = superAdmin();
    $wakil = superAdmin(['is_active' => false]);
    $koor = member(RoleName::KoordinatorMedpub);

    // Ketua menulis, Wakil nonaktif → tidak ada Ketua/Wakil lain.
    expect(fn () => workflow()->submit($ketua, draftBy($ketua)))
        ->toThrow(ValidationException::class, 'Tidak ada Ketua/Wakil aktif lain');

    // Tidak ada Koordinator Medpub aktif.
    $koor->update(['is_active' => false]);
    $anggota = member(RoleName::AnggotaMedpub);

    expect(fn () => workflow()->submit($anggota, draftBy($anggota)))
        ->toThrow(ValidationException::class, 'Belum ada Koordinator Medpub aktif');
});

it('penolakan & revisi wajib catatan; ditolak memberi tahu penulis', function (): void {
    Notification::fake();
    ['koor' => $koor] = contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);
    workflow()->submit($anggota, $post);

    expect(fn () => workflow()->reject($koor, $post->fresh(), ''))->toThrow(ValidationException::class);

    workflow()->reject($koor, $post->fresh(), 'Tidak sesuai pedoman konten BEM.');
    expect($post->fresh()->status)->toBe(PostStatus::Rejected);
    Notification::assertSentTo($anggota, PostWorkflowNotification::class, fn ($n) => $n->title === 'Konten ditolak');
});

it('konten belum lengkap tidak bisa diajukan', function (): void {
    contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);

    expect(fn () => workflow()->submit($anggota, draftBy($anggota, PostType::Social, ['social_caption' => null])))
        ->toThrow(ValidationException::class, 'caption medsos')
        ->and(fn () => workflow()->submit($anggota, draftBy($anggota, PostType::Article, ['body' => null])))
        ->toThrow(ValidationException::class, 'isi atau lampiran');
});

it('"Turunkan & revisi": alasan wajib, turun dari website, tercatat, memberi tahu penulis', function (): void {
    Notification::fake();
    ['koor' => $koor, 'ketua' => $ketua] = contentTeam();
    $anggota = member(RoleName::AnggotaMedpub);
    $post = draftBy($anggota);
    workflow()->submit($anggota, $post);
    workflow()->approveByCoordinator($koor, $post->fresh());
    workflow()->approveByLeader($ketua, $post->fresh());
    workflow()->publish($koor, $post->fresh());

    expect(fn () => workflow()->takeDown($anggota, $post->fresh(), 'Ada salah ketik di judul.'))->toThrow(AuthorizationException::class)
        ->and(fn () => workflow()->takeDown($koor, $post->fresh(), ''))->toThrow(ValidationException::class);

    workflow()->takeDown($koor, $post->fresh(), 'Ada salah ketik nama narasumber.');
    $post->refresh();

    expect($post->status)->toBe(PostStatus::Revision)
        ->and($post->isPublished())->toBeFalse()
        ->and($post->approvals->last()->decision)->toBe(ApprovalDecision::TakenDown);
    $this->get($post->publicUrl())->assertNotFound();
    $this->assertDatabaseHas('activity_logs', ['action' => 'post.taken_down', 'subject_id' => $post->id]);
    Notification::assertSentTo($anggota, PostWorkflowNotification::class, fn ($n) => str_contains($n->body, 'salah ketik'));

    // Super Admin juga boleh menurunkan.
    expect(Gate::forUser($ketua)->allows('takeDown', $post->forceFill(['status' => PostStatus::Published])))->toBeTrue();
});

it('tandai sudah diposting hanya untuk konten terbit, link harus http/https', function (): void {
    ['koor' => $koor] = contentTeam();
    $post = draftBy(member(RoleName::AnggotaMedpub), PostType::Social, ['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    expect(fn () => workflow()->markSocialPosted($koor, $post, 'javascript:alert(1)'))->toThrow(ValidationException::class)
        ->and(fn () => workflow()->markSocialPosted(member(RoleName::AnggotaMedpub), $post, 'https://instagram.com/p/abc'))->toThrow(AuthorizationException::class);

    workflow()->markSocialPosted($koor, $post, 'https://instagram.com/p/abc');

    expect($post->fresh())->social_post_url->toBe('https://instagram.com/p/abc')->social_posted_at->not->toBeNull();
});
