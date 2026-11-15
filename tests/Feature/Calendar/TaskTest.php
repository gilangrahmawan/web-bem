<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Filament\Pages\MyTasks;
use App\Models\Event;
use App\Models\EventAssignee;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskReminderNotification;
use App\Services\EventService;
use App\Services\TaskService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function contentTask(array $assigneeAttributes = [], ?string $deadline = null): EventAssignee
{
    $event = Event::factory()->category(EventCategory::Content)->create([
        'division_id' => divisionId('medpub'),
        'starts_at' => $deadline ?? now()->addDays(7)->toDateString(),
    ]);

    return EventAssignee::factory()->create([
        'event_id' => $event->id,
        'user_id' => $assigneeAttributes['user_id'] ?? member(RoleName::AnggotaMedpub)->id,
        ...$assigneeAttributes,
    ]);
}

it('penanggung jawab mendapat notifikasi saat ditugaskan', function (): void {
    Notification::fake();
    $anggota = member(RoleName::AnggotaMedpub);

    app(EventService::class)->create(member(RoleName::KoordinatorMedpub), [
        'title' => 'Poster Hari Guru',
        'category' => 'content',
        'starts_at' => now()->addDays(5)->toDateString(),
        'assignee_ids' => [$anggota->id],
    ]);

    Notification::assertSentTo($anggota, TaskAssignedNotification::class);
});

it('pengingat H-2 dan H-1 terkirim sekali saja', function (): void {
    $this->travelTo(now()->setTime(7, 0));
    $h2 = contentTask(deadline: now()->addDays(2)->toDateString());
    $h1 = contentTask(deadline: now()->addDay()->toDateString());
    $later = contentTask(deadline: now()->addDays(5)->toDateString());

    artisan('tasks:remind')->assertSuccessful();
    artisan('tasks:remind')->assertSuccessful(); // dijalankan ulang di hari yang sama

    expect($h2->user->notifications()->count())->toBe(1)
        ->and($h1->user->notifications()->count())->toBe(1)
        ->and($later->user->notifications()->count())->toBe(0)
        ->and($h2->fresh()->reminded_h2_at)->not->toBeNull()
        ->and($h1->fresh()->reminded_h1_at)->not->toBeNull();

    // Besok: tugas H-2 kini H-1 → dapat satu pengingat lagi, tidak lebih.
    $this->travel(1)->days();
    artisan('tasks:remind')->assertSuccessful();
    artisan('tasks:remind')->assertSuccessful();

    expect($h2->user->notifications()->count())->toBe(2)
        ->and($h1->user->notifications()->count())->toBe(1);
});

it('pengingat tidak dikirim untuk tugas yang sudah diserahkan/selesai atau user nonaktif', function (): void {
    Notification::fake();
    $deadline = now()->addDay()->toDateString();

    $submitted = contentTask(['status' => TaskStatus::Submitted], $deadline);
    $done = contentTask(['status' => TaskStatus::Done], $deadline);
    $inactive = contentTask(['user_id' => member(RoleName::AnggotaMedpub, ['is_active' => false])->id], $deadline);
    $open = contentTask(['status' => TaskStatus::InProgress], $deadline);

    artisan('tasks:remind')->assertSuccessful();

    Notification::assertSentTo($open->user, TaskReminderNotification::class);
    Notification::assertNotSentTo([$submitted->user, $done->user, $inactive->user], TaskReminderNotification::class);
});

it('user lain tidak bisa mengubah status atau mengunduh hasil tugas orang lain', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('task-results/hasil.pdf', '%PDF-1.4 uji');
    $task = contentTask(['status' => TaskStatus::Submitted, 'result_path' => 'task-results/hasil.pdf', 'result_original_name' => 'hasil.pdf']);
    $stranger = member(RoleName::AnggotaMedpub);

    expect(fn () => app(TaskService::class)->withdraw($stranger, $task))->toThrow(AuthorizationException::class);
    expect(fn () => app(TaskService::class)->review($stranger, $task, TaskStatus::Done))->toThrow(AuthorizationException::class);

    actingAs($stranger)->get(route('panel.tasks.result', $task))->assertNotFound();
    actingAs(member(RoleName::AnggotaHumas))->get(route('panel.tasks.result', $task))->assertNotFound();

    actingAs($task->user)->get(route('panel.tasks.result', $task))->assertOk()->assertDownload('hasil.pdf');
    actingAs(member(RoleName::KoordinatorMedpub))->get(route('panel.tasks.result', $task))->assertOk();
    actingAs(member(RoleName::Pembina))->get(route('panel.tasks.result', $task))->assertOk(); // read-only

    $this->app['auth']->guard()->logout();
    $this->get(route('panel.tasks.result', $task))->assertRedirect();
});

it('alur tugas: mulai, serahkan berkas, tandai selesai oleh koordinator', function (): void {
    Storage::fake('local');
    $task = contentTask();
    actingAs($task->user);

    livewire(MyTasks::class)
        ->callAction(TestAction::make('start')->table($task))
        ->assertHasNoFormErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);

    livewire(MyTasks::class)
        ->callAction(TestAction::make('submit')->table($task), data: [
            'result_file' => UploadedFile::fake()->create('desain-final.pdf', 500, 'application/pdf'),
            'result_url' => 'https://www.canva.com/design/contoh',
        ])
        ->assertHasNoFormErrors();

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::Submitted)
        ->and($task->result_original_name)->toBe('desain-final.pdf')
        ->and($task->result_path)->not->toContain('desain-final')
        ->and($task->result_url)->toBe('https://www.canva.com/design/contoh');
    Storage::disk('local')->assertExists($task->result_path);

    actingAs(member(RoleName::KoordinatorMedpub));
    livewire(MyTasks::class)
        ->filterTable('mine', false) // default koordinator: hanya tugasnya sendiri
        ->callAction(TestAction::make('markDone')->table($task))
        ->assertHasNoFormErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
    $this->assertDatabaseHas('activity_logs', ['action' => 'task.reviewed', 'subject_id' => $task->id]);
});

it('berkas hasil dengan tipe tidak diizinkan ditolak', function (): void {
    Storage::fake('local');
    $task = contentTask(['status' => TaskStatus::InProgress]);
    actingAs($task->user);

    livewire(MyTasks::class)
        ->callAction(TestAction::make('submit')->table($task), data: [
            'result_file' => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
        ])
        ->assertHasFormErrors(['result_file']);

    livewire(MyTasks::class)
        ->callAction(TestAction::make('submit')->table($task), data: [
            'result_file' => UploadedFile::fake()->create('besar.zip', 11000, 'application/zip'),
        ])
        ->assertHasFormErrors(['result_file']);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('anggota hanya melihat tugasnya sendiri di Tugas Saya', function (): void {
    $mine = contentTask();
    $others = contentTask();
    actingAs($mine->user);

    livewire(MyTasks::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$others]);
});

it('koordinator Medpub melihat Tugas Saya dengan filter "Hanya tugas saya" lalu semua tugas', function (): void {
    $koor = member(RoleName::KoordinatorMedpub);
    $mine = contentTask(['user_id' => $koor->id]);
    $others = contentTask();
    actingAs($koor);

    livewire(MyTasks::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$others])
        ->filterTable('mine', false)
        ->assertCanSeeTableRecords([$mine, $others]);
});
