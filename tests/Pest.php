<?php

use App\Enums\FormPurpose;
use App\Enums\RoleName;
use App\Models\Division;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Period;
use App\Models\Setting;
use App\Models\User;
use App\Services\KasService;
use Database\Seeders\DivisionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Semua test Feature memakai database SQLite in-memory (lihat phpunit.xml)
| yang di-refresh setiap test, plus role/permission & divisi bawaan.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed([DivisionSeeder::class, RolePermissionSeeder::class]);
        Filament::setCurrentPanel('admin');
    })
    ->in('Feature');

/** Membuat user aktif dengan satu role bawaan. */
function userWithRole(RoleName|string $role, array $attributes = []): User
{
    $roleName = $role instanceof RoleName ? $role->value : $role;

    $user = User::factory()->create($attributes);
    $user->assignRole(Role::findByName($roleName, 'web'));

    // Role wajib 2FA (Fase 10) dianggap sudah mengatur 2FA, kecuali test menentukan sendiri.
    if (! array_key_exists('two_factor_secret', $attributes) && $user->requiresTwoFactor()) {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    }

    return $user;
}

function superAdmin(array $attributes = []): User
{
    return userWithRole(RoleName::SuperAdmin, $attributes);
}

/** @return list<RoleName> */
function nonSuperAdminRoles(): array
{
    return array_values(array_filter(
        RoleName::cases(),
        fn (RoleName $role): bool => $role !== RoleName::SuperAdmin,
    ));
}

/** Menguji komponen Livewire/Filament (pengganti plugin pest-plugin-livewire). */
function livewire(string $component, array $params = []): Testable
{
    return Livewire\Livewire::test($component, $params);
}

/** ID divisi bawaan berdasarkan slug (bph, kajian, medpub, humas). */
function divisionId(string $slug): int
{
    return (int) Division::query()->where('slug', $slug)->value('id');
}

/** User dengan role bawaan yang otomatis ditempatkan di divisi default role tersebut. */
function member(RoleName $role, array $attributes = []): User
{
    $slug = $role->divisionSlug();

    // Pengaju kegiatan wajib terhubung ke organisasi aktif (tanpa itu tidak bisa masuk panel).
    if ($role === RoleName::PengajuKegiatan && ! array_key_exists('organization_id', $attributes)) {
        $attributes['organization_id'] = Organization::factory()->create()->id;
    }

    return userWithRole($role, [...($slug ? ['division_id' => divisionId($slug)] : []), ...$attributes]);
}

/** Service kas dengan memo dibersihkan (setting bisa berubah di tengah test). */
function kas(): KasService
{
    $service = app(KasService::class);
    $service->flush();

    return $service;
}

/**
 * Periode aktif 1 Agu 2026 – 31 Jul 2027, "hari ini" Rabu 25 Nov 2026.
 * Tanggal mulai kas default Senin 2 Nov 2026 → minggu ke-1..4 sudah dimulai (2, 9, 16, 23 Nov).
 */
function kasSetup(?string $startDate = '2026-11-02', string $today = '2026-11-25 10:00:00'): Period
{
    test()->travelTo($today);
    $period = Period::factory()->active()->create(['starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);

    if ($startDate !== null) {
        Setting::set('kas_start_date', $startDate);
    }

    kas();

    return $period;
}

/**
 * Turnstile palsu: tidak pernah memanggil server Cloudflare.
 * Permintaan HTTP lain yang tidak di-fake akan gagal (preventStrayRequests).
 */
function fakeTurnstile(bool $success = true): void
{
    config([
        'services.turnstile.site_key' => '1x00000000000000000000AA',
        'services.turnstile.secret_key' => 'rahasia-uji',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => $success]),
    ]);
}

/**
 * Form uji (Fase 11A) berisi pertanyaan $questions (atribut FormQuestion; minimal 'type').
 * $state: draft | published | closed.
 */
function formWith(array $questions = [], array $attributes = [], array $settings = [], string $state = 'published', ?FormPurpose $purpose = null): Form
{
    $factory = Form::factory()->settings($settings);
    $factory = match ($state) {
        'published' => $factory->published(),
        'closed' => $factory->closed(),
        default => $factory,
    };

    $form = $factory->create($attributes);

    if ($purpose) {
        $form->forceFill(['purpose' => $purpose])->save();
    }

    $section = $form->sections()->first();

    foreach (array_values($questions) as $i => $question) {
        $form->questions()->create([
            'form_section_id' => $section->getKey(),
            'position' => $i,
            'label' => 'Pertanyaan '.($i + 1),
            'is_required' => false,
            'options' => [],
            'validation' => [],
            'points' => 0,
            ...$question,
        ]);
    }

    return $form->refresh();
}

/** Pertanyaan ke-$index (urut posisi) dari form. */
function formQuestion(Form $form, int $index = 0): FormQuestion
{
    return $form->questions()->get()[$index];
}
