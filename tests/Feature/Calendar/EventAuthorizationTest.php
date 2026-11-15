<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function eventData(EventCategory $category, array $overrides = []): array
{
    return [
        'title' => 'Agenda Uji',
        'category' => $category->value,
        'all_day' => true,
        'starts_at' => now()->addDays(7)->toDateString(),
        ...$overrides,
    ];
}

it('koordinator tidak bisa mengubah atau menghapus agenda divisi lain', function (): void {
    $koorKajian = member(RoleName::KoordinatorKajian);
    $own = Event::factory()->category(EventCategory::Meeting)->create(['division_id' => divisionId('kajian')]);
    $other = Event::factory()->category(EventCategory::Meeting)->create(['division_id' => divisionId('humas')]);
    $general = Event::factory()->category(EventCategory::Meeting)->create(['division_id' => null]);

    $gate = Gate::forUser($koorKajian);

    expect($gate->allows('update', $own))->toBeTrue()
        ->and($gate->allows('delete', $own))->toBeTrue()
        ->and($gate->allows('update', $other))->toBeFalse()
        ->and($gate->allows('delete', $other))->toBeFalse()
        ->and($gate->allows('update', $general))->toBeFalse();

    expect(fn () => app(EventService::class)->delete($koorKajian, $other))->toThrow(AuthorizationException::class);
    expect(fn () => app(EventService::class)->update($koorKajian, $other, eventData(EventCategory::Meeting)))
        ->toThrow(AuthorizationException::class);
    expect(Event::whereKey($other->id)->exists())->toBeTrue();
});

it('rapat dari koordinator selalu masuk divisinya walau division_id dimanipulasi', function (): void {
    $koorMedpub = member(RoleName::KoordinatorMedpub);

    $event = app(EventService::class)->create($koorMedpub, eventData(EventCategory::Meeting, [
        'division_id' => divisionId('humas'),
    ]));

    expect($event->division_id)->toBe(divisionId('medpub'))
        ->and($event->visibility->value)->toBe('internal');
});

it('sekretaris boleh membuat rapat untuk divisi mana pun atau rapat umum', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $service = app(EventService::class);

    $forHumas = $service->create($sekretaris, eventData(EventCategory::Meeting, ['division_id' => divisionId('humas')]));
    $general = $service->create($sekretaris, eventData(EventCategory::Meeting, ['division_id' => null]));
    $internal = $service->create($sekretaris, eventData(EventCategory::Internal, ['division_id' => divisionId('kajian')]));

    expect($forHumas->division_id)->toBe(divisionId('humas'))
        ->and($general->division_id)->toBeNull()
        ->and($internal->division_id)->toBe(divisionId('kajian'));
});

it('agenda UKM hanya bisa dibuat pemegang event.create_ukm', function (RoleName $role, bool $allowed): void {
    $user = member($role);
    $attempt = fn () => app(EventService::class)->create($user, eventData(EventCategory::Ukm));

    $allowed
        ? expect($attempt()->category)->toBe(EventCategory::Ukm)
        : expect($attempt)->toThrow(AuthorizationException::class);
})->with([
    'super_admin' => [RoleName::SuperAdmin, true],
    'sekretaris' => [RoleName::Sekretaris, true],
    'koordinator humas' => [RoleName::KoordinatorHumas, true],
    'koordinator kajian' => [RoleName::KoordinatorKajian, false],
    'anggota humas' => [RoleName::AnggotaHumas, false],
    'bendahara' => [RoleName::Bendahara, false],
]);

it('semua pemegang event.create_ukm boleh mengubah agenda UKM siapa pun', function (): void {
    $ukm = app(EventService::class)->create(member(RoleName::Sekretaris), eventData(EventCategory::Ukm));

    $updated = app(EventService::class)->update(member(RoleName::KoordinatorHumas), $ukm, eventData(EventCategory::Ukm, [
        'title' => 'Diubah Humas',
    ]));

    expect($updated->title)->toBe('Diubah Humas');
});

it('anggota tidak bisa membuat agenda publik, dan pembina tidak bisa membuat apa pun', function (): void {
    $anggota = member(RoleName::AnggotaMedpub);
    $pembina = member(RoleName::Pembina);

    foreach ([EventCategory::Holiday, EventCategory::Campus, EventCategory::Bem, EventCategory::Ukm] as $category) {
        expect(Gate::forUser($anggota)->allows('create', [Event::class, $category]))->toBeFalse();
    }

    expect(Gate::forUser($pembina)->allows('create', Event::class))->toBeFalse();

    foreach (EventCategory::cases() as $category) {
        expect(Gate::forUser($pembina)->allows('create', [Event::class, $category]))->toBeFalse();
    }

    $event = Event::factory()->category(EventCategory::Bem)->create();
    expect(Gate::forUser($pembina)->allows('update', $event))->toBeFalse()
        ->and(Gate::forUser($pembina)->allows('delete', $event))->toBeFalse();
});

it('tugas konten hanya dari pemegang event.assign_task dan wajib punya penanggung jawab aktif', function (): void {
    $koorMedpub = member(RoleName::KoordinatorMedpub);
    $active = member(RoleName::AnggotaMedpub);
    $inactive = member(RoleName::AnggotaMedpub, ['is_active' => false]);

    expect(fn () => app(EventService::class)->create(member(RoleName::KoordinatorHumas), eventData(EventCategory::Content, [
        'assignee_ids' => [$active->id],
    ])))->toThrow(AuthorizationException::class);

    expect(fn () => app(EventService::class)->create($koorMedpub, eventData(EventCategory::Content, [
        'assignee_ids' => [$inactive->id],
    ])))->toThrow(ValidationException::class);

    $event = app(EventService::class)->create($koorMedpub, eventData(EventCategory::Content, [
        'assignee_ids' => [$active->id, $inactive->id],
    ]));

    expect($event->assignees()->pluck('user_id')->all())->toBe([$active->id]);
});

it('link pendaftaran hanya menerima http/https', function (): void {
    expect(fn () => app(EventService::class)->create(superAdmin(), eventData(EventCategory::Bem, [
        'registration_url' => 'javascript:alert(1)',
    ])))->toThrow(ValidationException::class);
});

it('menyimpan, mengubah, dan menghapus agenda tercatat di log aktivitas', function (): void {
    $admin = superAdmin();
    $service = app(EventService::class);

    $event = $service->create($admin, eventData(EventCategory::Bem));
    $service->update($admin, $event, eventData(EventCategory::Bem, ['title' => 'Baru']));
    $service->delete($admin, $event);

    $this->assertDatabaseHas('activity_logs', ['action' => 'event.created', 'user_id' => $admin->id]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'event.updated', 'user_id' => $admin->id]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'event.deleted', 'user_id' => $admin->id]);
});
