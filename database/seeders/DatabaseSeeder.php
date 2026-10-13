<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ServerSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initial Superadmin User
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::create([
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin123!Secure'),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]);
        }

        // 2. Default Server Settings
        $settings = [
            [
                'key' => 'server_hostname',
                'value' => gethostname() ?: 'vps.local',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Server Hostname',
                'description' => 'Fully Qualified Domain Name (FQDN) of this VPS.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'server_ip',
                'value' => '127.0.0.1',
                'type' => 'string',
                'group' => 'network',
                'label' => 'Public IPv4 Address',
                'description' => 'Public IP of this VPS for DNS zone generation.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'panel_port',
                'value' => '8443',
                'type' => 'integer',
                'group' => 'security',
                'label' => 'Panel Port',
                'description' => 'Port on which the VPS Control Panel is served.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'default_php_version',
                'value' => '8.3',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Default PHP Version',
                'description' => 'Default PHP-FPM version used for newly provisioned websites.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'supported_php_versions',
                'value' => json_encode(['8.2', '8.3', '8.4']),
                'type' => 'json',
                'group' => 'general',
                'label' => 'Supported PHP Versions',
                'description' => 'List of PHP versions installed on the host system.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'dns_nameserver_1',
                'value' => 'ns1.example.com',
                'type' => 'string',
                'group' => 'network',
                'label' => 'Primary Nameserver',
                'description' => 'Default primary NS record for new DNS zones.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'dns_nameserver_2',
                'value' => 'ns2.example.com',
                'type' => 'string',
                'group' => 'network',
                'label' => 'Secondary Nameserver',
                'description' => 'Default secondary NS record for new DNS zones.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'ufw_status',
                'value' => 'active',
                'type' => 'string',
                'group' => 'security',
                'label' => 'UFW Firewall Status',
                'description' => 'Current status of Uncomplicated Firewall.',
                'is_sensitive' => false,
            ],
            [
                'key' => 'restic_repo_path',
                'value' => '/var/backups/restic',
                'type' => 'string',
                'group' => 'backup',
                'label' => 'Local Restic Repository Path',
                'description' => 'Default local directory for encrypted Restic backups.',
                'is_sensitive' => false,
            ],
        ];

        foreach ($settings as $setting) {
            ServerSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
