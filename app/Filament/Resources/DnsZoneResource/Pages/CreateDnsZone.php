<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use App\Services\DnsManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDnsZone extends CreateRecord
{
    protected static string $resource = DnsZoneResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $records = $data['records'] ?? [];
        unset($data['records']);

        $manager = app(DnsManager::class);

        if (empty($records)) {
            // Automatically populate standard default DNS records template
            return $manager->createZone($data['domain_name'], $data['website_id'] ?? null);
        }

        $zone = static::getModel()::create($data);
        foreach ($records as $rec) {
            $zone->records()->create($rec);
        }
        $manager->syncZone($zone);

        return $zone;
    }
}
