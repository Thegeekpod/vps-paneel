<?php

namespace App\Filament\Widgets;

use App\Services\SystemMonitorService;
use Filament\Widgets\Widget;

class ServiceStatusWidget extends Widget
{
    protected static ?int $sort = 3;
    protected static string $view = 'filament.widgets.service-status-widget';
    protected static ?string $pollingInterval = '15s';
    protected int | string | array $columnSpan = 'full';

    public function getServices(): array
    {
        $monitor = app(SystemMonitorService::class);
        $metrics = $monitor->getMetrics();
        $statuses = $metrics['services'] ?? [];

        $labels = [
            'nginx' => ['label' => 'Nginx Web Server', 'desc' => 'HTTP/HTTPS Reverse Proxy & Vhosts'],
            'mariadb' => ['label' => 'MariaDB Database', 'desc' => 'Relational SQL Database Engine'],
            'php8.3-fpm' => ['label' => 'PHP 8.3 FPM', 'desc' => 'FastCGI Process Manager Pool'],
            'php8.4-fpm' => ['label' => 'PHP 8.4 FPM', 'desc' => 'FastCGI Process Manager Pool'],
            'pdns' => ['label' => 'PowerDNS Server', 'desc' => 'Authoritative DNS Nameserver'],
            'postfix' => ['label' => 'Postfix Mail Transfer', 'desc' => 'SMTP Outbound & Submission Agent'],
            'dovecot' => ['label' => 'Dovecot IMAP/POP3', 'desc' => 'Secure Mailbox Retrieval Server'],
            'ufw' => ['label' => 'UFW Firewall', 'desc' => 'Host Packet Filter & Port Shield'],
        ];

        $result = [];
        foreach ($labels as $svc => $info) {
            $status = $statuses[$svc] ?? 'active';
            $result[] = [
                'service' => $svc,
                'name' => $info['label'],
                'description' => $info['desc'],
                'status' => $status,
                'is_active' => $status === 'active',
            ];
        }

        return $result;
    }
}
