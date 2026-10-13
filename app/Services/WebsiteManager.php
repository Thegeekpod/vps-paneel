<?php

namespace App\Services;

use App\Enums\DomainType;
use App\Enums\WebsiteStatus;
use App\Models\Domain;
use App\Models\Website;
use Illuminate\Support\Str;

class WebsiteManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Create a new website with isolated system user and virtual host.
     */
    public function create(array $data): Website
    {
        $domain = strtolower(trim($data['primary_domain']));
        $name = $data['name'] ?? Str::title(explode('.', $domain)[0]);
        $phpVersion = $data['php_version'] ?? config('vps.default_php_version', '8.3');

        // Generate clean Linux username if not provided
        $baseUser = $data['system_user'] ?? preg_replace('/[^a-z0-9]/', '', explode('.', $domain)[0]);
        $systemUser = substr('u_' . $baseUser, 0, 16);

        // Ensure unique username
        $counter = 1;
        while (Website::where('system_user', $systemUser)->exists()) {
            $systemUser = substr('u_' . $baseUser . $counter, 0, 16);
            $counter++;
        }

        $documentRoot = "/home/{$systemUser}/web/{$domain}/public_html";

        $website = Website::create([
            'name' => $name,
            'primary_domain' => $domain,
            'system_user' => $systemUser,
            'php_version' => $phpVersion,
            'document_root' => $documentRoot,
            'status' => WebsiteStatus::Provisioning,
            'ssl_enabled' => false,
            'notes' => $data['notes'] ?? null,
        ]);

        // Register primary domain record
        $website->domains()->create([
            'domain_name' => $domain,
            'is_subdomain' => substr_count($domain, '.') > 1,
            'type' => DomainType::Primary,
            'ssl_enabled' => false,
        ]);

        try {
            // Execute server agent
            $result = $this->agent->execute('CreateWebsite', [
                'domain' => $domain,
                'username' => $systemUser,
                'php_version' => $phpVersion,
            ], 'Website', (string) $website->id);

            $website->update(['status' => WebsiteStatus::Active]);
        } catch (\Throwable $e) {
            $website->update(['status' => WebsiteStatus::Failed]);
            throw $e;
        }

        return $website;
    }

    /**
     * Suspend a website.
     */
    public function suspend(Website $website): void
    {
        $this->agent->execute('SuspendWebsite', [
            'domain' => $website->primary_domain,
        ], 'Website', (string) $website->id);

        $website->update(['status' => WebsiteStatus::Suspended]);
    }

    /**
     * Unsuspend a website.
     */
    public function unsuspend(Website $website): void
    {
        $this->agent->execute('UnsuspendWebsite', [
            'domain' => $website->primary_domain,
        ], 'Website', (string) $website->id);

        $website->update(['status' => WebsiteStatus::Active]);
    }

    /**
     * Delete a website and clean up system configs.
     */
    public function delete(Website $website): void
    {
        $this->agent->execute('DeleteWebsite', [
            'domain' => $website->primary_domain,
            'username' => $website->system_user,
            'php_version' => $website->php_version,
        ], 'Website', (string) $website->id);

        $website->delete();
    }
}
