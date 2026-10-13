<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDnsZones extends ListRecords
{
    protected static string $resource = DnsZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Create DNS Zone'),
        ];
    }
}
