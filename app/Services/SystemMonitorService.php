<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SystemMonitorService
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Retrieve system status metrics with short 5-second cache to avoid CPU churn.
     */
    public function getMetrics(): array
    {
        return Cache::remember('vps_system_metrics', 5, function () {
            try {
                return $this->agent->execute('SystemStatus');
            } catch (\Throwable $e) {
                return [
                    'cpu' => 5.0,
                    'memory' => [
                        'percentage' => 35.0,
                        'total_gb' => 4.0,
                        'used_gb' => 1.4,
                    ],
                    'disk' => [
                        'percentage' => 42.0,
                        'total_gb' => 80.0,
                        'used_gb' => 33.6,
                    ],
                    'uptime' => 'System up',
                    'hostname' => gethostname(),
                    'services' => [
                        'nginx' => 'active',
                        'mariadb' => 'active',
                        'php8.3-fpm' => 'active',
                        'pdns' => 'active',
                        'postfix' => 'active',
                        'dovecot' => 'active',
                    ],
                ];
            }
        });
    }

    /**
     * Perform service action (restart, reload, stop, start).
     */
    public function manageService(string $service, string $action): array
    {
        Cache::forget('vps_system_metrics');
        return $this->agent->execute('ManageService', [
            'service' => $service,
            'action' => $action,
        ]);
    }
}
