<?php

namespace App\Filament\Resources\WebsiteResource\Pages;

use App\Filament\Resources\WebsiteResource;
use App\Models\Website;
use App\Services\WebsiteManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWebsite extends CreateRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $manager = app(WebsiteManager::class);
        return $manager->create($data);
    }
}
