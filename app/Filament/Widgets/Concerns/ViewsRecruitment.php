<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Models\User;
use App\Policies\RecruitmentApplicantPolicy;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget oprec: pengelola oprec & Pembina (semua pendaftar) atau koordinator pewawancara
 * (hanya pendaftar yang memilih divisinya). Data = gelombang terbaru yang sudah dibuka.
 */
trait ViewsRecruitment
{
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && ! $user->isApplicant()
            && (RecruitmentApplicantPolicy::seesAll($user) || RecruitmentApplicantPolicy::isInterviewer($user))
            && self::latestRecruitment() !== null;
    }

    protected static function latestRecruitment(): ?Recruitment
    {
        return Recruitment::query()->where('opens_at', '<=', now())->orderByDesc('opens_at')->first();
    }

    /** @return Builder<RecruitmentApplicant> */
    protected function applicantsQuery(): Builder
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return RecruitmentApplicant::query()->visibleTo($user)
            ->where('recruitment_id', self::latestRecruitment()?->getKey() ?? 0);
    }

    public function getDescription(): ?string
    {
        return self::latestRecruitment()?->name;
    }
}
