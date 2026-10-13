<?php

namespace App\Console\Commands;

use App\Models\SslCertificate;
use App\Services\SslManager;
use Illuminate\Console\Command;

class CheckSslRenewals extends Command
{
    protected $signature = 'vps:ssl-renew';
    protected $description = 'Check and automatically renew expiring Let\'s Encrypt SSL certificates';

    public function handle(SslManager $sslManager): int
    {
        $this->info('Checking expiring certificates...');

        $expiring = SslCertificate::where('auto_renew', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(14))
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('All SSL certificates are up to date.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiring->count()} certificates due for renewal. Triggering renewals...");
        $result = $sslManager->renewAll();
        $this->info('Renewal process completed.');

        return self::SUCCESS;
    }
}
