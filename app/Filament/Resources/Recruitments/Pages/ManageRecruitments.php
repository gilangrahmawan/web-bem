<?php

namespace App\Filament\Resources\Recruitments\Pages;

use App\Filament\Resources\Recruitments\RecruitmentResource;
use App\Models\Recruitment;
use App\Services\ActivityLogger;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageRecruitments extends ManageRecords
{
    protected static string $resource = RecruitmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat gelombang')
                ->using(function (array $data): Recruitment {
                    $recruitment = new Recruitment($data);
                    $recruitment->forceFill(['created_by' => Filament::auth()->id()])->save();

                    return $recruitment;
                })
                ->after(fn (Recruitment $record) => app(ActivityLogger::class)->log('recruitment.created', $record, ['name' => $record->name])),
        ];
    }
}
