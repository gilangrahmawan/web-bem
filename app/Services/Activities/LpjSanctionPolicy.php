<?php

namespace App\Services\Activities;

use App\Models\Organization;
use App\Models\StudentActivity;
use Illuminate\Support\Collection;

/**
 * Titik kait sanksi LPJ terlambat (keputusan Fase 7: BELUM ADA SANKSI).
 *
 * Dipanggil setiap kali proposal diajukan. Saat ini selalu mengizinkan (mengembalikan null).
 * Untuk mengaktifkan sanksi nanti, cukup ubah blocksNewProposal(), misalnya:
 *
 *   $overdue = $this->overdueActivities($organization);
 *   return $overdue->isEmpty() ? null
 *       : 'Proposal baru belum bisa diajukan karena masih ada LPJ terlambat: '.$overdue->pluck('name')->implode(', ');
 *
 * lalu tambahkan test di tests/Feature/Activities. Tidak perlu mengubah alur lain.
 */
class LpjSanctionPolicy
{
    /** Pesan penolakan bila organisasi diblokir mengajukan proposal baru; null = boleh. */
    public function blocksNewProposal(Organization $organization, ?StudentActivity $current = null): ?string
    {
        return null;
    }

    /** @return Collection<int, StudentActivity> */
    public function overdueActivities(Organization $organization): Collection
    {
        return StudentActivity::query()->where('organization_id', $organization->getKey())->lpjOverdue()->get();
    }
}
