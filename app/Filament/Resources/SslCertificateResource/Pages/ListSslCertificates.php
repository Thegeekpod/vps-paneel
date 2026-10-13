<?php

namespace App\Filament\Resources\SslCertificateResource\Pages;

use App\Filament\Resources\SslCertificateResource;
use App\Models\Website;
use App\Services\SslManager;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListSslCertificates extends ListRecords
{
    protected static string $resource = SslCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Issue / Install Certificate'),
        ];
    }
}
