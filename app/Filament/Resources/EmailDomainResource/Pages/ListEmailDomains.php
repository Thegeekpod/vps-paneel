<?php

namespace App\Filament\Resources\EmailDomainResource\Pages;

use App\Filament\Resources\EmailDomainResource;
use App\Services\MailManager;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListEmailDomains extends ListRecords
{
    protected static string $resource = EmailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add Email Domain'),
        ];
    }
}
