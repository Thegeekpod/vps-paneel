<?php

namespace App\Filament\Resources\BackupJobResource\Pages;

use App\Filament\Resources\BackupJobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBackupJob extends EditRecord
{
    protected static string $resource = BackupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
