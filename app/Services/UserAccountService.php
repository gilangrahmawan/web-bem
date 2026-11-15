<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Logika akun pengurus: buat, ubah role, reset password, aktif/nonaktif.
 *
 * Aturan yang dijaga di sini (bukan hanya di UI):
 * - Hanya super_admin yang boleh memberi/mencabut role super_admin.
 * - Sistem selalu menyisakan minimal satu Super Admin aktif.
 * - User tidak bisa menonaktifkan dirinya sendiri.
 * - User tidak pernah dihapus.
 */
class UserAccountService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Membuat akun pengurus dengan password sementara (wajib diganti saat login pertama).
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roles
     * @return array{0: User, 1: string} [user, password sementara]
     */
    public function create(User $actor, array $attributes, array $roles): array
    {
        $temporary = $this->generateTemporaryPassword();

        $user = DB::transaction(function () use ($actor, $attributes, $roles, $temporary): User {
            $this->guardRoleAssignment($actor, [], $roles);

            $user = new User;
            $user->fill(Arr::except($attributes, ['password', 'is_active', 'must_change_password']));
            $user->forceFill([
                'password' => $temporary,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $user->syncRoles($roles);

            $this->logger->log('user.created', $user, [
                'email' => $user->email,
                'roles' => array_values($roles),
            ], $actor);

            return $user;
        });

        return [$user, $temporary];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $actor, User $user, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $user, $attributes): User {
            $user->fill(Arr::except($attributes, ['password', 'is_active', 'must_change_password']));
            $changed = array_keys($user->getDirty());
            $user->save();

            if ($changed !== []) {
                $this->logger->log('user.updated', $user, ['fields' => $changed], $actor);
            }

            return $user;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $actor, User $user, array $roles): void
    {
        DB::transaction(function () use ($actor, $user, $roles): void {
            // Kunci baris super admin agar dua permintaan bersamaan tidak lolos bersamaan.
            $this->lockActiveSuperAdmins();

            $current = $user->getRoleNames()->all();
            $this->guardRoleAssignment($actor, $current, $roles, $user);

            sort($current);
            $sorted = $roles;
            sort($sorted);

            if ($current === $sorted) {
                return;
            }

            $user->syncRoles($roles);

            $this->logger->log('user.roles_changed', $user, [
                'from' => $current,
                'to' => array_values($roles),
            ], $actor);
        });
    }

    /** Mengembalikan password sementara (tampilkan sekali ke Super Admin). */
    public function resetPassword(User $actor, User $user): string
    {
        $temporary = $this->generateTemporaryPassword();

        DB::transaction(function () use ($actor, $user, $temporary): void {
            $user->forceFill([
                'password' => $temporary,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            $this->invalidateSessions($user);

            $this->logger->log('user.password_reset', $user, [], $actor);
        });

        return $temporary;
    }

    public function deactivate(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $this->lockActiveSuperAdmins();
            $user->refresh();

            if (! $user->is_active) {
                return;
            }

            if ($actor->is($user)) {
                throw new BusinessRuleException('Anda tidak bisa menonaktifkan akun Anda sendiri.');
            }

            if ($user->isSuperAdmin()) {
                if (! $actor->isSuperAdmin()) {
                    throw new BusinessRuleException('Hanya Super Admin yang bisa menonaktifkan Super Admin.');
                }

                $this->ensureAnotherActiveSuperAdmin($user);
            }

            $user->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            $this->invalidateSessions($user);

            $this->logger->log('user.deactivated', $user, [], $actor);
        });
    }

    public function activate(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            if ($user->is_active) {
                return;
            }

            if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
                throw new BusinessRuleException('Hanya Super Admin yang bisa mengaktifkan Super Admin.');
            }

            $user->forceFill(['is_active' => true])->save();

            $this->logger->log('user.activated', $user, [], $actor);
        });
    }

    /** Menghapus semua session user (driver database), kecuali session tertentu. */
    public function invalidateSessions(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $new
     */
    private function guardRoleAssignment(User $actor, array $current, array $new, ?User $target = null): void
    {
        if ($new === []) {
            throw new BusinessRuleException('Setiap pengurus wajib memiliki minimal satu role.');
        }

        $superAdmin = RoleName::SuperAdmin->value;
        $hadSuperAdmin = in_array($superAdmin, $current, true);
        $hasSuperAdmin = in_array($superAdmin, $new, true);

        if ($hadSuperAdmin !== $hasSuperAdmin && ! $actor->isSuperAdmin()) {
            throw new BusinessRuleException('Hanya Super Admin yang bisa memberi atau mencabut role Super Admin.');
        }

        if ($target && $hadSuperAdmin && ! $hasSuperAdmin && $target->is_active) {
            $this->ensureAnotherActiveSuperAdmin($target);
        }
    }

    private function ensureAnotherActiveSuperAdmin(User $user): void
    {
        $others = User::query()->activeSuperAdmins()->whereKeyNot($user->getKey())->count();

        if ($others === 0) {
            throw new BusinessRuleException('Ditolak: sistem harus menyisakan minimal satu Super Admin aktif.');
        }
    }

    private function lockActiveSuperAdmins(): void
    {
        User::query()->activeSuperAdmins()->lockForUpdate()->pluck('users.id');
    }

    public function generateTemporaryPassword(): string
    {
        // Memenuhi aturan: min. 10 karakter, huruf, angka, simbol. Tanpa karakter ambigu.
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $symbols = '!@#$%*?';
        $all = $letters.$digits.$symbols;

        $chars = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($chars); $i < 14; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher-Yates dengan random_int (shuffle() bawaan tidak kriptografis).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
