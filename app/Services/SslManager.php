<?php

namespace App\Services;

use App\Enums\SslProvider;
use App\Enums\SslStatus;
use App\Models\SslCertificate;
use App\Models\Website;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SslManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Request and issue a Let's Encrypt SSL certificate for a website.
     */
    public function issueLetsEncrypt(Website $website, ?string $domain = null): SslCertificate
    {
        $targetDomain = $domain ?: $website->primary_domain;

        $certificate = SslCertificate::updateOrCreate(
            ['domain_name' => $targetDomain],
            [
                'website_id' => $website->id,
                'provider' => SslProvider::LetsEncrypt,
                'status' => SslStatus::Pending,
                'auto_renew' => true,
            ]
        );

        try {
            $result = $this->agent->execute('IssueSslCertificate', [
                'domain' => $targetDomain,
                'webroot' => $website->document_root,
                'email' => 'admin@' . $targetDomain,
            ], 'SslCertificate', (string) $certificate->id);

            $certificate->update([
                'certificate_path' => $result['certificate_path'] ?? null,
                'private_key_path' => $result['private_key_path'] ?? null,
                'fullchain_path' => $result['fullchain_path'] ?? null,
                'issuer' => $result['issuer'] ?? "Let's Encrypt",
                'expires_at' => isset($result['expires_at']) ? Carbon::parse($result['expires_at']) : Carbon::now()->addDays(90),
                'status' => SslStatus::Active,
                'last_renewed_at' => Carbon::now(),
                'failure_reason' => null,
            ]);

            $website->update(['ssl_enabled' => true]);

            return $certificate;
        } catch (\Throwable $e) {
            $certificate->update([
                'status' => SslStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Install a custom SSL certificate and private key.
     */
    public function installCustomCertificate(Website $website, string $domain, string $certPem, string $keyPem): SslCertificate
    {
        $certData = @openssl_x509_parse($certPem);
        if (! $certData) {
            throw ValidationException::withMessages(['certificate' => 'Invalid X.509 certificate PEM format']);
        }

        $validKey = @openssl_x509_check_private_key($certPem, $keyPem);
        if (! $validKey) {
            throw ValidationException::withMessages(['private_key' => 'Private key does not match the provided certificate']);
        }

        $expiresAt = isset($certData['validTo_time_t']) ? Carbon::createFromTimestamp($certData['validTo_time_t']) : null;
        $issuer = $certData['issuer']['O'] ?? ($certData['issuer']['CN'] ?? 'Custom CA');

        $cert = SslCertificate::updateOrCreate(
            ['domain_name' => $domain],
            [
                'website_id' => $website->id,
                'provider' => SslProvider::Custom,
                'issuer' => $issuer,
                'expires_at' => $expiresAt,
                'status' => SslStatus::Active,
                'auto_renew' => false,
            ]
        );

        $website->update(['ssl_enabled' => true]);

        return $cert;
    }

    /**
     * Trigger renewal of eligible certificates.
     */
    public function renewAll(): array
    {
        return $this->agent->execute('RenewSslCertificates');
    }
}
