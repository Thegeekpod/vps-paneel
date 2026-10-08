<?php

namespace App\Services;

use App\Models\FirewallRule;

class FirewallService
{
    /**
     * Get or sync active firewall rules.
     */
    public function getRules(): array
    {
        $this->ensureDefaultRules();
        return FirewallRule::orderBy('port')->get()->toArray();
    }

    public function addRule(array $data): FirewallRule
    {
        $port = trim($data['port']);
        $proto = $data['protocol'] ?? 'tcp';
        $action = $data['action'] ?? 'allow';

        if (PHP_OS_FAMILY === 'Linux') {
            @shell_exec("ufw {$action} {$port}/{$proto} 2>&1");
        }

        return FirewallRule::create([
            'name' => $data['name'] ?? "Port {$port}",
            'port' => $port,
            'protocol' => $proto,
            'action' => $action,
            'from_ip' => $data['from_ip'] ?? '0.0.0.0/0',
            'status' => 'active',
        ]);
    }

    public function deleteRule(FirewallRule $rule): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            @shell_exec("ufw delete {$rule->action} {$rule->port}/{$rule->protocol} 2>&1");
        }

        $rule->delete();
    }

    public function ensureDefaultRules(): void
    {
        if (FirewallRule::count() === 0) {
            FirewallRule::create(['name' => 'SSH Remote Access', 'port' => '22', 'protocol' => 'tcp', 'action' => 'allow']);
            FirewallRule::create(['name' => 'HTTP Web Traffic', 'port' => '80', 'protocol' => 'tcp', 'action' => 'allow']);
            FirewallRule::create(['name' => 'HTTPS Secure Traffic', 'port' => '443', 'protocol' => 'tcp', 'action' => 'allow']);
            FirewallRule::create(['name' => 'VPS Control Panel', 'port' => '8080', 'protocol' => 'tcp', 'action' => 'allow']);
        }
    }
}
