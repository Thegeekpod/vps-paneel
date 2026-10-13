<?php

namespace App\Filament\Pages;

use App\Models\ServerSetting;
use App\Services\ServerAgentService;
use App\Services\SystemMonitorService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ServerSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Server & Services';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.server-settings';

    public string $hostname = '';
    public string $serverIp = '';
    public int $panelPort = 8443;
    public string $defaultPhp = '8.3';
    public array $firewallRules = [];
    public ?int $newPort = null;
    public string $newProto = 'tcp';
    public array $versionInfo = [];

    public function mount(): void
    {
        $this->hostname = ServerSetting::get('server_hostname', gethostname());
        $this->serverIp = ServerSetting::get('server_ip', '127.0.0.1');
        $this->panelPort = (int) ServerSetting::get('panel_port', 8443);
        $this->defaultPhp = ServerSetting::get('default_php_version', '8.3');
        $this->versionInfo = app(\App\Services\PanelUpdateService::class)->getCurrentVersion();

        $this->loadFirewall();
    }

    public function loadFirewall(): void
    {
        try {
            $agent = app(ServerAgentService::class);
            $res = $agent->execute('ManageFirewall', ['action' => 'status']);
            $rules = $res['rules'] ?? [];
            $this->firewallRules = ! empty($rules) ? $rules : $this->getDefaultRules();
        } catch (\Throwable $e) {
            $this->firewallRules = $this->getDefaultRules();
        }
    }

    public function getDefaultRules(): array
    {
        return [
            ['port' => 22, 'proto' => 'tcp', 'service' => 'SSH Remote Management', 'action' => 'ALLOW'],
            ['port' => 80, 'proto' => 'tcp', 'service' => 'HTTP Web Server (Nginx)', 'action' => 'ALLOW'],
            ['port' => 443, 'proto' => 'tcp', 'service' => 'HTTPS SSL Web Server', 'action' => 'ALLOW'],
            ['port' => 8443, 'proto' => 'tcp', 'service' => 'VPS Control Panel', 'action' => 'ALLOW'],
            ['port' => 53, 'proto' => 'tcp/udp', 'service' => 'PowerDNS Nameserver', 'action' => 'ALLOW'],
            ['port' => 25, 'proto' => 'tcp', 'service' => 'Postfix SMTP Mail', 'action' => 'ALLOW'],
            ['port' => 587, 'proto' => 'tcp', 'service' => 'Postfix Mail Submission', 'action' => 'ALLOW'],
            ['port' => 993, 'proto' => 'tcp', 'service' => 'Dovecot IMAPS (Secure Mailbox)', 'action' => 'ALLOW'],
        ];
    }

    public function ensureDefaultRules(): void
    {
        try {
            $agent = app(ServerAgentService::class);
            $agent->execute('ManageFirewall', ['action' => 'ensure_defaults']);
            $this->loadFirewall();
            Notification::make()->title('All core web hosting ports (80, 443, 22, 53, 8443, Mail) are enabled & active in UFW!')->success()->send();
        } catch (\Throwable $e) {
            $this->firewallRules = $this->getDefaultRules();
            Notification::make()->title('Default hosting rules loaded')->info()->send();
        }
    }

    public function deleteRule(int $port, string $proto = 'tcp'): void
    {
        try {
            app(ServerAgentService::class)->execute('ManageFirewall', [
                'action' => 'delete',
                'port' => $port,
                'proto' => $proto,
            ]);
            $this->loadFirewall();
            Notification::make()->title("Rule for port {$port}/{$proto} removed")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Failed to delete rule')->body($e->getMessage())->danger()->send();
        }
    }

    public function saveSettings(): void
    {
        ServerSetting::set('server_hostname', $this->hostname);
        ServerSetting::set('server_ip', $this->serverIp);
        ServerSetting::set('panel_port', $this->panelPort);
        ServerSetting::set('default_php_version', $this->defaultPhp);

        Notification::make()->title('Server settings updated successfully')->success()->send();
    }

    public function restartService(string $service): void
    {
        try {
            app(SystemMonitorService::class)->manageService($service, 'restart');
            Notification::make()->title("Service '{$service}' restarted successfully")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Restart failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function reloadService(string $service): void
    {
        try {
            app(SystemMonitorService::class)->manageService($service, 'reload');
            Notification::make()->title("Service '{$service}' reloaded successfully")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Reload failed')->body($e->getMessage())->danger()->send();
        }
    }

    public function allowPort(): void
    {
        if (! $this->newPort || $this->newPort < 1 || $this->newPort > 65535) {
            Notification::make()->title('Invalid port number')->danger()->send();
            return;
        }

        $portToAdd = $this->newPort;
        $protoToAdd = $this->newProto;

        try {
            app(ServerAgentService::class)->execute('ManageFirewall', [
                'action' => 'allow',
                'port' => $portToAdd,
                'proto' => $protoToAdd,
            ]);

            $this->loadFirewall();
            $this->newPort = null;
            Notification::make()->title("Port {$portToAdd}/{$protoToAdd} allowed in UFW")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Failed to allow port')->body($e->getMessage())->danger()->send();
        }
    }

    public function updatePanel(): void
    {
        try {
            $updater = app(\App\Services\PanelUpdateService::class);
            $res = $updater->update();
            $this->versionInfo = $updater->getCurrentVersion();

            Notification::make()
                ->title('Panel Updated Successfully!')
                ->body("Updated from {$res['previous_hash']} to {$res['new_hash']}.")
                ->success()
                ->duration(8000)
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
