<?php

namespace App\Filament\Widgets;

use App\Models\BackupSnapshot;
use App\Models\Database;
use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SslCertificate;
use App\Models\Website;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeWebsites = Website::where('status', 'active')->count();
        $totalWebsites = Website::count();
        $totalDomains = Domain::count();
        $activeSsl = SslCertificate::where('status', 'active')->count();
        $totalDbs = Database::count();
        $totalMailboxes = Mailbox::count();
        $totalBackups = BackupSnapshot::count();

        return [
            Stat::make('Hosted Websites', "{$activeWebsites} / {$totalWebsites}")
                ->description('Active / Total')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success'),

            Stat::make('Domains & Aliases', $totalDomains)
                ->description('Total routing entries')
                ->descriptionIcon('heroicon-m-link')
                ->color('info'),

            Stat::make('SSL Certificates', $activeSsl)
                ->description('Active HTTPS bindings')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            Stat::make('Databases', $totalDbs)
                ->description('MariaDB user databases')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),

            Stat::make('Mailboxes', $totalMailboxes)
                ->description('Active virtual accounts')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('warning'),

            Stat::make('Restic Backups', $totalBackups)
                ->description('Total repository snapshots')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),
        ];
    }
}
