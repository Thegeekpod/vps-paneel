<?php

namespace App\Filament\Resources\DatabaseResource\Pages;

use App\Filament\Resources\DatabaseResource;
use App\Models\Website;
use App\Services\DatabaseManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDatabase extends CreateRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $manager = app(DatabaseManager::class);
        $website = ! empty($data['website_id']) ? Website::find($data['website_id']) : null;

        $db = $manager->createDatabase(
            $data['name'],
            $website,
            $data['charset'] ?? 'utf8mb4',
            $data['collation'] ?? 'utf8mb4_unicode_ci'
        );

        if (! empty($data['db_user']) && ! empty($data['db_password'])) {
            $manager->createUser($data['db_user'], $data['db_password'], $db);
        }

        return $db;
    }
}
