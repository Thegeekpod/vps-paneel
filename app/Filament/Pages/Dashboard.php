<?php

namespace App\Filament\Pages;

use App\Services\PanelUpdateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        $updater = app(PanelUpdateService::class);
        $version = $updater->getCurrentVersion();

        return [
            Action::make('update_panel')
                ->label('Update Panel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->badge($version['hash'])
                ->modalHeading('One-Click Panel Update')
                ->modalDescription("Current commit: {$version['hash']} ({$version['date']}) on branch '{$version['branch']}'. Pull latest updates and recompile assets now?")
                ->requiresConfirmation()
                ->action(function (PanelUpdateService $updater) {
                    try {
                        $result = $updater->update();

                        Notification::make()
                            ->title('Control Panel Updated Successfully!')
                            ->body("Updated from {$result['previous_hash']} to {$result['new_hash']} in {$result['duration_ms']}ms.")
                            ->success()
                            ->duration(10000)
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Update Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
