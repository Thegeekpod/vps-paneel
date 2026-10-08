<?php

namespace App\Services;

use App\Models\Site;
use App\Models\TaskLog;

class SslCertbotService
{
    public function __construct(
        protected NginxService $nginxService
    ) {}

    /**
     * Issue Let's Encrypt SSL certificate via Certbot.
     */
    public function issueCertificate(Site $site, ?string $email = null): array
    {
        $startTime = microtime(true);
        $email = $email ?: "admin@{$site->domain}";
        $domains = [$site->domain];
        if (!empty($site->aliases)) {
            $domains = array_merge($domains, array_filter(explode(' ', $site->aliases)));
        }

        $domainArgs = implode(' -d ', array_map('trim', $domains));
        $cmd = "certbot certonly --nginx -d {$domainArgs} --non-interactive --agree-tos --email {$email} --no-eff-email";

        $output = '';
        $success = true;

        if (PHP_OS_FAMILY === 'Linux') {
            $output = @shell_exec("{$cmd} 2>&1");
            if (!str_contains((string) $output, 'Successfully received certificate') && !str_contains((string) $output, 'Certificate not yet due for renewal')) {
                $success = false;
            }
        } else {
            // Local dev simulation
            $output = "Saving debug log to /var/log/letsencrypt/letsencrypt.log\n"
                    . "Requesting a certificate for {$domainArgs}\n"
                    . "Successfully received certificate (Dev Simulator).\n"
                    . "Certificate is saved at: /etc/letsencrypt/live/{$site->domain}/fullchain.pem\n"
                    . "Key is saved at:         /etc/letsencrypt/live/{$site->domain}/privkey.pem\n"
                    . "Renewal scheduled for 90 days.";
        }

        if ($success) {
            $site->update([
                'ssl_enabled' => true,
                'ssl_issued_at' => now(),
                'ssl_expires_at' => now()->addDays(90),
            ]);

            // Re-generate Nginx vhost with SSL enabled
            $this->nginxService->deployVhost($site);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000);

        TaskLog::create([
            'site_id' => $site->id,
            'title' => "Let's Encrypt SSL Certificate: {$site->domain}",
            'type' => 'ssl',
            'status' => $success ? 'completed' : 'failed',
            'output' => $output,
            'execution_time_ms' => $durationMs,
        ]);

        return [
            'success' => $success,
            'output' => $output,
        ];
    }

    /**
     * Disable SSL and fall back to standard HTTP.
     */
    public function disableCertificate(Site $site): void
    {
        $site->update([
            'ssl_enabled' => false,
        ]);

        $this->nginxService->deployVhost($site);

        TaskLog::create([
            'site_id' => $site->id,
            'title' => "Disabled SSL for: {$site->domain}",
            'type' => 'ssl',
            'status' => 'completed',
            'output' => "Reverted {$site->domain} to port 80 HTTP configuration.",
            'execution_time_ms' => 50,
        ]);
    }
}
