<?php

namespace App\Filament\Resources\StudentActivities;

use App\Enums\ActivityStatus;
use App\Enums\RoleName;
use App\Models\User;

/** Antrean review per peran (untuk badge menu & tab "Menunggu Saya"). */
class StudentActivityPolicyHelper
{
    /** @return list<string> status yang menunggu keputusan user ini */
    public static function statusesFor(User $user): array
    {
        $statuses = [];

        if ($user->isSuperAdmin() || $user->checkPermissionTo('submission.verify')) {
            array_push($statuses, ActivityStatus::ProposalSubmitted->value, ActivityStatus::LpjSubmitted->value);
        }

        if ($user->isSuperAdmin() || $user->checkPermissionTo('submission.review_budget')) {
            array_push($statuses, ActivityStatus::ProposalVerified->value, ActivityStatus::LpjVerified->value);
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            array_push($statuses, ActivityStatus::ProposalBudgetReviewed->value, ActivityStatus::LpjBudgetReviewed->value);
        }

        // Super Admin cadangan tahap Sekretaris/Bendahara, tetapi antreannya fokus pada keputusan.
        if ($user->isSuperAdmin()) {
            return [ActivityStatus::ProposalBudgetReviewed->value, ActivityStatus::LpjBudgetReviewed->value];
        }

        return array_values(array_unique($statuses));
    }

    public static function reviewsSomething(User $user): bool
    {
        return self::statusesFor($user) !== [];
    }
}
