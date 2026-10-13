<?php

namespace App\Filament\Resources\BackupJobResource\Pages;

use App\Filament\Resources\BackupJobResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class CreateBackupJob extends CreateRecord
{
    protected static string $resource = BackupJobResource::class;
}
