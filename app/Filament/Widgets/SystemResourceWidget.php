<?php

namespace App\Filament\Widgets;

use App\Services\SystemMonitorService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemResourceWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '10s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $monitor = app(SystemMonitorService::class);
        $metrics = $monitor->getMetrics();

        $cpu = $metrics['cpu'] ?? 0;
        $mem = $metrics['memory'] ?? ['percentage' => 0, 'used_gb' => 0, 'total_gb' => 4];
        $disk = $metrics['disk'] ?? ['percentage' => 0, 'used_gb' => 0, 'total_gb' => 50];
        $uptime = $metrics['uptime'] ?? 'Online';

        $cpuColor = $cpu > 80 ? 'danger' : ($cpu > 50 ? 'warning' : 'success');
        $memColor = $mem['percentage'] > 85 ? 'danger' : ($mem['percentage'] > 60 ? 'warning' : 'success');
        $diskColor = $disk['percentage'] > 85 ? 'danger' : ($disk['percentage'] > 70 ? 'warning' : 'success');

        return [
            Stat::make('CPU Load', $cpu . '%')
                ->description('Host Processor')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->chart([$cpu * 0.7, $cpu * 0.9, $cpu])
                ->color($cpuColor),

            Stat::make('Memory (RAM)', "{$mem['used_gb']} GB / {$mem['total_gb']} GB")
                ->description("{$mem['percentage']}% utilized")
                ->descriptionIcon('heroicon-m-variable')
                ->chart([$mem['percentage'] * 0.8, $mem['percentage']])
                ->color($memColor),

            Stat::make('Disk Storage', "{$disk['used_gb']} GB / {$disk['total_gb']} GB")
                ->description("{$disk['percentage']}% disk utilized")
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($diskColor),

            Stat::make('Server Uptime', $uptime)
                ->description($metrics['hostname'] ?? 'VPS Host')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
