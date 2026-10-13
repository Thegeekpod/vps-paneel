<?php

namespace App\Filament\Resources\BackupJobResource\Pages;

use App\Filament\Resources\BackupJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBackupJobs extends ListRecords
{
    protected static string $resource = BackupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Create Backup Job'),
        ];
    }
}
