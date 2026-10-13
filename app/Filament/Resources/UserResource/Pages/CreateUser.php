<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
