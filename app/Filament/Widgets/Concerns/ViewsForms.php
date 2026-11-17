<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Form;
use App\Models\User;
use App\Policies\FormPolicy;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget form: pengelola form (Super Admin, Sekretaris: semua; koordinator: divisinya) & Pembina (lihat semua).
 * Data selalu lewat Form::visibleTo().
 */
trait ViewsForms
{
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && (FormPolicy::seesAll($user) || FormPolicy::isDivisionManager($user));
    }

    /** @return Builder<Form> */
    protected function formsQuery(): Builder
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return Form::query()->visibleTo($user);
    }
}
