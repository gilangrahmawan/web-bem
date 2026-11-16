<?php

namespace App\Services\Chat;

use App\Enums\ConversationType;
use App\Enums\OrganizationType;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Division;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grup chat otomatis (AGENTS.md C6 + keputusan Fase 7A/9):
 * - Grup divisi (BPH, Kajian, Medpub, Humas) + "Semua Pengurus": pengurus BEM aktif saja —
 *   TANPA Pembina dan TANPA pengaju kegiatan.
 * - Grup organisasi (mis. "UKM Futsal"): akun pengaju aktif organisasi aktif itu; organisasi bertipe
 *   BEM tidak dibuatkan grup.
 * Dipanggil saat user dibuat/diubah (observer), role dipasang/dilepas (event Spatie), saat membuka
 * Chat, dan oleh `chat:sync` harian sebagai jaring pengaman. Idempoten.
 */
class ConversationSyncService
{
    public const ALL_STAFF = 'all_staff';

    /** Sinkronkan seluruh grup otomatis. @return int jumlah perubahan keanggotaan */
    public function syncAll(): int
    {
        $changes = 0;

        foreach ($this->expectedGroups() as $key => [$conversation, $memberIds]) {
            $changes += $this->applyMembers($conversation, $memberIds);
        }

        return $changes;
    }

    /** Sinkronkan keanggotaan satu user di semua grup otomatis. */
    public function syncUser(User $user): void
    {
        $user->unsetRelation('roles');

        DB::transaction(function () use ($user): void {
            $staff = $user->isChatStaff();
            $applicantOrg = $this->applicantOrganization($user);

            foreach ($this->ensureGroups() as $conversation) {
                $shouldBe = match (true) {
                    $conversation->system_key === self::ALL_STAFF => $staff,
                    $conversation->type === ConversationType::Division => $staff && $user->division_id === $conversation->division_id,
                    $conversation->type === ConversationType::Organization => $applicantOrg !== null && $applicantOrg === $conversation->organization_id,
                    default => false,
                };

                $member = $conversation->members()->where('user_id', $user->getKey());

                if ($shouldBe && ! $member->exists()) {
                    $conversation->members()->create(['user_id' => $user->getKey(), 'role' => ConversationMember::MEMBER, 'last_read_at' => now()]);
                } elseif (! $shouldBe) {
                    $member->delete();
                }
            }
        });
    }

    /** @return Collection<int, Conversation> grup otomatis (dibuat bila belum ada) */
    public function ensureGroups(): Collection
    {
        $groups = collect();

        foreach (Division::query()->orderBy('id')->get() as $division) {
            $groups->push($this->group('division:'.$division->slug, ConversationType::Division, $division->name, divisionId: $division->getKey()));
        }

        $groups->push($this->group(self::ALL_STAFF, ConversationType::Division, 'Semua Pengurus'));

        foreach (Organization::query()->where('is_active', true)->where('type', '!=', OrganizationType::Bem->value)->get() as $organization) {
            $groups->push($this->group('organization:'.$organization->getKey(), ConversationType::Organization, $organization->name, organizationId: $organization->getKey()));
        }

        return $groups;
    }

    /** @return array<string, array{0: Conversation, 1: list<int>}> */
    private function expectedGroups(): array
    {
        $users = User::query()->with('roles')->get();
        $staff = $users->filter(fn (User $u) => $u->isChatStaff());
        $result = [];

        foreach ($this->ensureGroups() as $conversation) {
            $ids = match (true) {
                $conversation->system_key === self::ALL_STAFF => $staff->pluck('id'),
                $conversation->type === ConversationType::Division => $staff->where('division_id', $conversation->division_id)->pluck('id'),
                default => $users->filter(fn (User $u) => $this->applicantOrganization($u) === $conversation->organization_id)->pluck('id'),
            };

            $result[$conversation->system_key] = [$conversation, $ids->map(fn ($id) => (int) $id)->values()->all()];
        }

        // Organisasi yang dinonaktifkan / berubah jadi tipe BEM: kosongkan grupnya (riwayat tetap ada).
        $activeKeys = array_keys($result);
        Conversation::query()->where('type', ConversationType::Organization->value)->whereNotIn('system_key', $activeKeys ?: [''])
            ->each(function (Conversation $c) use (&$result): void {
                $result[$c->system_key] = [$c, []];
            });

        return $result;
    }

    /** @param  list<int>  $memberIds */
    private function applyMembers(Conversation $conversation, array $memberIds): int
    {
        return DB::transaction(function () use ($conversation, $memberIds): int {
            $current = $conversation->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $add = array_diff($memberIds, $current);
            $remove = array_diff($current, $memberIds);

            foreach ($add as $id) {
                $conversation->members()->create(['user_id' => $id, 'role' => ConversationMember::MEMBER, 'last_read_at' => now()]);
            }

            if ($remove !== []) {
                $conversation->members()->whereIn('user_id', $remove)->delete();
            }

            return count($add) + count($remove);
        });
    }

    private function applicantOrganization(User $user): ?int
    {
        if (! $user->canUseChat() || ! $user->isApplicant() || $user->organization_id === null) {
            return null;
        }

        // Selalu baca ulang (relasi bisa ter-cache dari sebelum organization_id diubah).
        $organization = Organization::query()->find($user->organization_id);

        return $organization && $organization->is_active && $organization->type !== OrganizationType::Bem
            ? (int) $organization->getKey()
            : null;
    }

    private function group(string $key, ConversationType $type, string $name, ?int $divisionId = null, ?int $organizationId = null): Conversation
    {
        $conversation = Conversation::query()->firstOrNew(['system_key' => $key]);

        $conversation->forceFill([
            'type' => $type,
            'name' => mb_substr($name, 0, 100),
            'system_key' => $key,
            'division_id' => $divisionId,
            'organization_id' => $organizationId,
        ]);

        if ($conversation->isDirty()) {
            $conversation->save();
        }

        return $conversation;
    }
}
