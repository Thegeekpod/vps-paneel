<?php

namespace App\Console\Commands;

use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Services\ServerAgentService;
use Illuminate\Console\Command;

class SyncWebsites extends Command
{
    protected $signature = 'vps:sync-websites';
    protected $description = 'Sync all website Nginx vhosts, PHP-FPM pools, and permissions';

    public function handle(ServerAgentService $agent): int
    {
        $websites = Website::all();
        $this->info("Syncing {$websites->count()} website(s)...");

        foreach ($websites as $website) {
            $this->line("Syncing: {$website->primary_domain} ({$website->system_user})...");

            try {
                $agent->execute('CreateWebsite', [
                    'domain' => $website->primary_domain,
                    'username' => $website->system_user,
                    'php_version' => $website->php_version ?? '8.3',
                ], 'Website', (string) $website->id);

                $website->update(['status' => WebsiteStatus::Active]);
                $this->info(" -> OK: {$website->primary_domain}");
            } catch (\Throwable $e) {
                $this->error(" -> Failed: {$website->primary_domain}: " . $e->getMessage());
            }
        }

        // Also fix all file permissions
        try {
            $agent->execute('ManageWebsiteFile', ['action' => 'fix_permissions_all']);
            $this->info("Permissions synchronized across all websites.");
        } catch (\Throwable $e) {
            $this->warn("Permission sync notice: " . $e->getMessage());
        }

        return 0;
    }
}
