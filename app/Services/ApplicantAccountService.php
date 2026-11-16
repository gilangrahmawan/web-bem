<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Exceptions\BusinessRuleException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Akun pengaju kegiatan (keputusan Fase 7). Super Admin & Sekretaris (applicant.manage) boleh membuat,
 * mengubah data dasar, menonaktifkan, dan reset password — HANYA untuk akun yang role-nya murni
 * `pengaju_kegiatan`. Role selalu dipaksa di sini; pengurus BEM tidak bisa disentuh lewat jalur ini.
 */
class ApplicantAccountService
{
    private const ROLE = 'pengaju_kegiatan';

    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly ActivityLogger $logger,
    ) {}

    /** Akun yang role-nya hanya pengaju_kegiatan. */
    public static function isPureApplicant(User $user): bool
    {
        $roles = $user->getRoleNames()->all();

        return $roles === [self::ROLE];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: User, 1: string} [akun, password sementara]
     */
    public function create(User $actor, array $data): array
    {
        $this->authorize($actor);
        $attributes = $this->validated($data);

        return $this->accounts->create($actor, $attributes, [self::ROLE]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, User $applicant, array $data): User
    {
        $this->authorizeTarget($actor, $applicant);
        $attributes = $this->validated($data, $applicant);

        return DB::transaction(function () use ($actor, $applicant, $attributes): User {
            $before = $applicant->organization_id;
            $user = $this->accounts->update($actor, $applicant, $attributes);

            if ($before !== $user->organization_id) {
                $this->logger->log('applicant.organization_changed', $user, ['from' => $before, 'to' => $user->organization_id], $actor);
            }

            return $user;
        });
    }

    public function resetPassword(User $actor, User $applicant): string
    {
        $this->authorizeTarget($actor, $applicant);

        return $this->accounts->resetPassword($actor, $applicant);
    }

    public function deactivate(User $actor, User $applicant): void
    {
        $this->authorizeTarget($actor, $applicant);
        $this->accounts->deactivate($actor, $applicant);
    }

    public function activate(User $actor, User $applicant): void
    {
        $this->authorizeTarget($actor, $applicant);
        $this->accounts->activate($actor, $applicant);
    }

    /**
     * Hanya name, email, phone, organization_id. Organisasi wajib aktif & bukan BEM.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data, ?User $existing = null): array
    {
        $data = Arr::only($data, ['name', 'email', 'phone', 'organization_id']);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($existing?->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)
                ->whereNot('type', OrganizationType::Bem->value)],
        ], [], [
            'name' => 'nama',
            'organization_id' => 'organisasi',
        ])->validate();

        $validated['organization_id'] = (int) $validated['organization_id'];
        $validated['division_id'] = null;
        $validated['position'] = 'Pengaju kegiatan · '.Organization::query()->whereKey($validated['organization_id'])->value('name');

        return $validated;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->is_active || ! $actor->can('applicant.manage')) {
            throw new AuthorizationException('Anda tidak berhak mengelola akun pengaju kegiatan.');
        }
    }

    private function authorizeTarget(User $actor, User $target): void
    {
        $this->authorize($actor);

        if (! self::isPureApplicant($target)) {
            throw new AuthorizationException('Akun ini bukan akun pengaju kegiatan. Pengurus BEM hanya dikelola Super Admin di menu User.');
        }

        if ($target->getKey() === $actor->getKey()) {
            throw new BusinessRuleException('Anda tidak bisa mengelola akun Anda sendiri di sini.');
        }
    }
}
