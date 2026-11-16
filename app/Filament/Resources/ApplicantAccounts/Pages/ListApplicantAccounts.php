<?php

namespace App\Filament\Resources\ApplicantAccounts\Pages;

use App\Filament\Resources\ApplicantAccounts\ApplicantAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplicantAccounts extends ListRecords
{
    protected static string $resource = ApplicantAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat akun pengaju')];
    }
}
