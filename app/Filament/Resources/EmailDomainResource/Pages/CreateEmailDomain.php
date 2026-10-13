<?php

namespace App\Filament\Resources\EmailDomainResource\Pages;

use App\Filament\Resources\EmailDomainResource;
use App\Services\MailManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmailDomain extends CreateRecord
{
    protected static string $resource = EmailDomainResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(MailManager::class)->createEmailDomain($data['domain_name']);
    }
}
