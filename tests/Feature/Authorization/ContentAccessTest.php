<?php

use App\Enums\ProgramStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Models\Division;
use App\Models\Period;
use App\Models\Program;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

function division(string $slug): Division
{
    return Division::where('slug', $slug)->firstOrFail();
}

it('akses resource Unduhan, Galeri, dan Struktur sesuai role', function (string $url, array $allowed): void {
    foreach (RoleName::cases() as $role) {
        $response = actingAs(userWithRole($role, ['division_id' => division('bph')->id]))->get($url);

        in_array($role, $allowed, true) ? $response->assertOk() : $response->assertForbidden();
    }
})->with([
    'daftar unduhan' => ['/panel-bem/downloads', [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::Pembina]],
    'tambah unduhan' => ['/panel-bem/downloads/create', [RoleName::SuperAdmin, RoleName::Sekretaris]],
    'daftar galeri' => ['/panel-bem/galleries', [RoleName::SuperAdmin, RoleName::KoordinatorMedpub, RoleName::Pembina]],
    'tambah galeri' => ['/panel-bem/galleries/create', [RoleName::SuperAdmin, RoleName::KoordinatorMedpub]],
    'struktur' => ['/panel-bem/structure-members', [RoleName::SuperAdmin]],
]);

it('koordinator tidak bisa mengubah atau menghapus proker divisi lain', function (): void {
    $koorKajian = userWithRole(RoleName::KoordinatorKajian, ['division_id' => division('kajian')->id]);
    $own = Program::factory()->create(['division_id' => division('kajian')->id]);
    $other = Program::factory()->create(['division_id' => division('humas')->id]);

    expect(Gate::forUser($koorKajian)->allows('update', $own))->toBeTrue()
        ->and(Gate::forUser($koorKajian)->allows('delete', $own))->toBeTrue()
        ->and(Gate::forUser($koorKajian)->allows('update', $other))->toBeFalse()
        ->and(Gate::forUser($koorKajian)->allows('delete', $other))->toBeFalse();

    actingAs($koorKajian)->get("/panel-bem/programs/{$other->id}/edit")->assertNotFound();
    actingAs($koorKajian)->get("/panel-bem/programs/{$own->id}/edit")->assertOk();
});

it('koordinator hanya melihat proker divisinya di dashboard', function (): void {
    $period = Period::factory()->active()->create();
    $koorHumas = userWithRole(RoleName::KoordinatorHumas, ['division_id' => division('humas')->id]);
    $own = Program::factory()->create(['division_id' => division('humas')->id, 'period_id' => $period->id]);
    $other = Program::factory()->create(['division_id' => division('medpub')->id, 'period_id' => $period->id]);

    actingAs($koorHumas);

    livewire(ListPrograms::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);
});

it('proker baru dari koordinator otomatis masuk divisinya', function (): void {
    $period = Period::factory()->active()->create();
    actingAs(userWithRole(RoleName::KoordinatorMedpub, ['division_id' => division('medpub')->id]));

    livewire(CreateProgram::class)
        ->fillForm([
            'period_id' => $period->id,
            'title' => 'Konten Edukasi',
            'status' => ProgramStatus::Planned->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Program::where('title', 'Konten Edukasi')->value('division_id'))->toBe(division('medpub')->id);
});

it('koordinator tidak bisa menitipkan proker ke divisi lain walau field dimanipulasi', function (): void {
    $period = Period::factory()->active()->create();
    actingAs(userWithRole(RoleName::KoordinatorMedpub, ['division_id' => division('medpub')->id]));

    livewire(CreateProgram::class)
        ->set('data.division_id', division('kajian')->id)
        ->fillForm([
            'period_id' => $period->id,
            'title' => 'Proker Titipan',
            'status' => ProgramStatus::Planned->value,
        ])
        ->call('create');

    expect(Program::where('division_id', division('kajian')->id)->exists())->toBeFalse();
});

it('anggota dan pembina tidak bisa membuat atau mengubah proker', function (RoleName $role): void {
    $user = userWithRole($role, ['division_id' => division('kajian')->id]);
    $program = Program::factory()->create(['division_id' => division('kajian')->id]);

    expect(Gate::forUser($user)->allows('create', Program::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $program))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $program))->toBeFalse();

    actingAs($user)->get('/panel-bem/programs/create')->assertForbidden();
})->with([RoleName::AnggotaKajian, RoleName::Pembina, RoleName::Bendahara]);

it('pembina bisa melihat semua proker (read-only)', function (): void {
    $pembina = userWithRole(RoleName::Pembina);
    $a = Program::factory()->create(['division_id' => division('kajian')->id]);
    $b = Program::factory()->create(['division_id' => division('humas')->id]);

    actingAs($pembina)->get('/panel-bem/programs')->assertOk();

    livewire(ListPrograms::class)
        ->filterTable('period_id', null)
        ->assertCanSeeTableRecords([$a, $b]);
});
