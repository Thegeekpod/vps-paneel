<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use App\Services\DnsManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDnsZone extends EditRecord
{
    protected static string $resource = DnsZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Re-sync PowerDNS zone after saving
        app(DnsManager::class)->syncZone($this->record);
    }
}
