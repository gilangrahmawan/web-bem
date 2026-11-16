<?php

use App\Enums\ConversationType;
use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Events\ChatMessageChanged;
use App\Filament\Pages\Chat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationSyncService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Event::fake([ChatMessageChanged::class]);
    Notification::fake();
});

function chat(): ChatService
{
    return app(ChatService::class);
}

function groupMembers(string $key): array
{
    return Conversation::where('system_key', $key)->sole()->members()->pluck('user_id')->sort()->values()->all();
}

/** Otorisasi channel Pusher memakai callback routes/channels.php (tanpa memanggil server Pusher). */
function canJoinChannel(User $user, string $channel): bool
{
    $broadcaster = Broadcast::driver();
    $method = new ReflectionMethod($broadcaster, 'verifyUserCanAccessChannel');
    $request = Request::create('/broadcasting/auth', 'POST', ['channel_name' => 'private-'.$channel]);
    $request->setUserResolver(fn () => $user);

    try {
        $method->invoke($broadcaster, $request, $channel); // lolos = tidak melempar AccessDenied

        return true;
    } catch (AccessDeniedHttpException) {
        return false;
    }
}

it('grup divisi & Semua Pengurus otomatis: tanpa Pembina dan tanpa pengaju', function (): void {
    $kajian = member(RoleName::AnggotaKajian);
    $koor = member(RoleName::KoordinatorKajian);
    $humas = member(RoleName::AnggotaHumas);
    $sekretaris = member(RoleName::Sekretaris);
    $pembina = member(RoleName::Pembina);
    $pengaju = member(RoleName::PengajuKegiatan);

    expect(groupMembers('division:kajian'))->toBe(collect([$kajian->id, $koor->id])->sort()->values()->all())
        ->and(groupMembers('division:bph'))->toBe([$sekretaris->id])
        ->and(groupMembers('all_staff'))->toEqualCanonicalizing([$kajian->id, $koor->id, $humas->id, $sekretaris->id])
        ->and(groupMembers('all_staff'))->not->toContain($pembina->id, $pengaju->id);
});

it('user nonaktif keluar dari grup divisi; pindah divisi pindah grup; aktif kembali masuk lagi', function (): void {
    $user = member(RoleName::AnggotaKajian);
    expect(groupMembers('division:kajian'))->toContain($user->id);

    $user->update(['division_id' => divisionId('humas')]);
    expect(groupMembers('division:kajian'))->not->toContain($user->id)
        ->and(groupMembers('division:humas'))->toContain($user->id);

    $user->forceFill(['is_active' => false])->save();
    expect(groupMembers('division:humas'))->not->toContain($user->id)
        ->and(groupMembers('all_staff'))->not->toContain($user->id);

    $user->forceFill(['is_active' => true])->save();
    expect(groupMembers('all_staff'))->toContain($user->id);
});

it('pengaju masuk grup organisasinya, pindah saat organisasi berubah; organisasi bertipe BEM tanpa grup', function (): void {
    $futsal = Organization::factory()->create(['name' => 'UKM Futsal', 'type' => OrganizationType::Ukm]);
    $hmj = Organization::factory()->create(['name' => 'HMJ Informatika', 'type' => OrganizationType::Hmj]);
    $bem = Organization::factory()->create(['name' => 'BEM Uji Coba', 'type' => OrganizationType::Bem]);

    $a = member(RoleName::PengajuKegiatan, ['organization_id' => $futsal->id]);
    $b = member(RoleName::PengajuKegiatan, ['organization_id' => $futsal->id]);

    $group = Conversation::where('system_key', 'organization:'.$futsal->id)->sole();
    expect($group->type)->toBe(ConversationType::Organization)->and($group->name)->toBe('UKM Futsal')
        ->and(groupMembers('organization:'.$futsal->id))->toEqualCanonicalizing([$a->id, $b->id])
        ->and(Conversation::where('system_key', 'organization:'.$bem->id)->exists())->toBeFalse();

    $b->update(['organization_id' => $hmj->id]);
    expect(groupMembers('organization:'.$futsal->id))->toBe([$a->id])
        ->and(groupMembers('organization:'.$hmj->id))->toBe([$b->id]);

    // Organisasi dinonaktifkan → grupnya dikosongkan.
    $futsal->update(['is_active' => false]);
    expect(groupMembers('organization:'.$futsal->id))->toBe([]);
});

it('hanya anggota yang bisa membaca/mengirim; Super Admin TIDAK bisa membuka chat personal orang lain', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::AnggotaHumas);
    $admin = superAdmin();
    $conversation = chat()->startPersonal($a, $b);
    chat()->send($a, $conversation, 'Rahasia berdua');

    expect(Gate::forUser($admin)->allows('view', $conversation))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('send', $conversation))->toBeFalse();
    expect(fn () => chat()->send($admin, $conversation, 'Ikut'))->toThrow(AuthorizationException::class);

    actingAs($admin);
    livewire(Chat::class, ['conversationId' => $conversation->id])
        ->assertSet('conversationId', null)
        ->assertDontSee('Rahasia berdua');

    $message = Message::where('conversation_id', $conversation->id)->sole();
    expect(canJoinChannel($admin, 'conversation.'.$conversation->id))->toBeFalse()
        ->and(canJoinChannel($a, 'conversation.'.$conversation->id))->toBeTrue()
        ->and(canJoinChannel($a, 'chat.user.'.$a->id))->toBeTrue()
        ->and(canJoinChannel($a, 'chat.user.'.$b->id))->toBeFalse();

    expect(fn () => chat()->deleteMessage($admin, $message))->toThrow(AuthorizationException::class);
});

it('Pembina tidak punya akses chat sama sekali', function (): void {
    $pembina = member(RoleName::Pembina);
    $staff = member(RoleName::Sekretaris);

    actingAs($pembina);
    get(Chat::getUrl())->assertForbidden();

    expect($pembina->canUseChat())->toBeFalse()
        ->and(canJoinChannel($pembina, 'chat.user.'.$pembina->id))->toBeFalse();
    expect(fn () => chat()->startPersonal($staff, $pembina))->toThrow(ValidationException::class);
    expect(fn () => chat()->createGroup($staff, 'Dengan Pembina', [$pembina->id]))->toThrow(ValidationException::class);
});

it('pengaju: chat personal dengan pengurus & pengaju lain, grup sendiri dari akun terdaftar, tanpa melihat percakapan orang lain', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    $pengajuLain = member(RoleName::PengajuKegiatan);
    $sekretaris = member(RoleName::Sekretaris);
    $humas = member(RoleName::KoordinatorHumas);

    $withStaff = chat()->startPersonal($pengaju, $sekretaris);
    chat()->startPersonal($pengaju, $pengajuLain);
    $group = chat()->createGroup($pengaju, 'Panitia Turnamen', [$pengajuLain->id, $humas->id]);
    expect($group->membership($pengaju)->isAdmin())->toBeTrue();

    $private = chat()->startPersonal($sekretaris, $humas);
    chat()->send($sekretaris, $private, 'Bahas internal BPH');

    actingAs($pengaju);
    get(Chat::getUrl())->assertOk();
    livewire(Chat::class)
        ->assertSee($sekretaris->name)->assertSee('Panitia Turnamen')
        ->assertDontSee('Bahas internal BPH')->assertDontSee('Semua Pengurus');
    livewire(Chat::class, ['conversationId' => $private->id])->assertSet('conversationId', null);

    expect(Conversation::forUser($pengaju)->pluck('id')->all())->not->toContain($private->id)
        ->and(Conversation::forUser($pengaju)->where('type', ConversationType::Division->value)->exists())->toBeFalse();
});

it('percakapan personal tidak dobel dan tidak bisa dengan diri sendiri', function (): void {
    $a = member(RoleName::AnggotaMedpub);
    $b = member(RoleName::AnggotaKajian);

    $first = chat()->startPersonal($a, $b);
    $second = chat()->startPersonal($b, $a);

    expect($second->id)->toBe($first->id)->and($first->members()->count())->toBe(2);
    expect(fn () => chat()->startPersonal($a, $a))->toThrow(ValidationException::class);
});

it('admin grup mengelola anggota; anggota biasa tidak; grup otomatis tidak bisa dibubarkan; Super Admin bisa membubarkan grup buatan', function (): void {
    $owner = member(RoleName::AnggotaMedpub);
    $m1 = member(RoleName::AnggotaKajian);
    $m2 = member(RoleName::AnggotaHumas);
    $group = chat()->createGroup($owner, 'Tim Desain', [$m1->id]);

    expect(fn () => chat()->addMembers($m1, $group, [$m2->id]))->toThrow(AuthorizationException::class);
    chat()->addMembers($owner, $group, [$m2->id]);
    chat()->removeMember($owner, $group, $m1);
    expect($group->members()->pluck('user_id')->all())->toEqualCanonicalizing([$owner->id, $m2->id]);

    // Admin keluar → anggota tersisa jadi admin.
    chat()->leave($owner, $group);
    expect($group->membership($m2)->isAdmin())->toBeTrue();

    $allStaff = Conversation::where('system_key', 'all_staff')->sole();
    expect(Gate::forUser(superAdmin())->allows('dissolve', $allStaff))->toBeFalse();

    chat()->dissolve(superAdmin(), $group);
    expect(Conversation::find($group->id))->toBeNull();
});

it('sinkron penuh (chat:maintenance) memperbaiki keanggotaan yang meleset', function (): void {
    $user = member(RoleName::AnggotaKajian);
    Conversation::where('system_key', 'division:kajian')->sole()->members()->delete();

    $this->artisan('chat:maintenance')->assertSuccessful();

    expect(groupMembers('division:kajian'))->toBe([$user->id]);
    expect(app(ConversationSyncService::class)->syncAll())->toBe(0);
});
