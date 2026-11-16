<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use App\Policies\StudentActivityPolicy;
use Filament\Facades\Filament;

/** Widget kegiatan untuk BEM: Super Admin, Sekretaris, Bendahara, Pembina (StudentActivityPolicy::seesAll). */
trait ViewsAllActivities
{
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && ! $user->isApplicant() && StudentActivityPolicy::seesAll($user);
    }
}
