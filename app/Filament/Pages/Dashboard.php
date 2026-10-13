<?php

namespace App\Filament\Pages;

use App\Services\PanelUpdateService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\MaxWidth;

class Dashboard extends BaseDashboard
{
    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function getSubheading(): ?string
    {
        try {
            $updater = app(PanelUpdateService::class);
            $version = $updater->getCurrentVersion();
            return "VPS Node Management & Live Telemetry • Version {$version['hash']} ({$version['date']}) on {$version['branch']}";
        } catch (\Throwable) {
            return "VPS Node Management & Live Telemetry";
        }
    }

    protected function getHeaderActions(): array
    {
        $updater = app(PanelUpdateService::class);
        $version = $updater->getCurrentVersion();

        return [
            Action::make('update_panel')
                ->label('Update Panel')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->modalHeading('One-Click Panel Update')
                ->modalDescription("Current commit: {$version['hash']} ({$version['date']}) on branch '{$version['branch']}'. Pull latest Git updates, run migrations, and refresh caches?")
                ->modalSubmitActionLabel('Execute Update')
                ->requiresConfirmation()
                ->action(function (PanelUpdateService $updater) {
                    try {
                        $result = $updater->update();

                        $logSnippet = implode("\n", array_slice($result['logs'] ?? [], -4));

                        Notification::make()
                            ->title('Control Panel Updated Successfully!')
                            ->body("Updated from {$result['previous_hash']} to {$result['new_hash']} in {$result['duration_ms']}ms.\n{$logSnippet}")
                            ->success()
                            ->duration(12000)
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
