<?php

namespace App\Filament\Resources\SslCertificateResource\Pages;

use App\Filament\Resources\SslCertificateResource;
use App\Models\Website;
use App\Services\SslManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSslCertificate extends CreateRecord
{
    protected static string $resource = SslCertificateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $manager = app(SslManager::class);
        $website = Website::findOrFail($data['website_id']);

        if (($data['provider'] ?? 'letsencrypt') === 'custom') {
            return $manager->installCustomCertificate(
                $website,
                $data['domain_name'],
                $data['custom_certificate'],
                $data['custom_private_key']
            );
        }

        return $manager->issueLetsEncrypt($website, $data['domain_name']);
    }
}
