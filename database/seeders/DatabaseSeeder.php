<?php

namespace Database\Seeders;

use App\Models\Database;
use App\Models\FirewallRule;
use App\Models\Site;
use App\Models\TaskLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Admin User
        User::firstOrCreate(
            ['email' => 'admin@vps-panel.local'],
            [
                'name' => 'VPS Admin',
                'password' => bcrypt('password'),
            ]
        );

        // 2. Seed Default Firewall Rules
        if (FirewallRule::count() === 0) {
            FirewallRule::create(['name' => 'SSH Remote Access', 'port' => '22', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '0.0.0.0/0']);
            FirewallRule::create(['name' => 'HTTP Web Traffic', 'port' => '80', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '0.0.0.0/0']);
            FirewallRule::create(['name' => 'HTTPS Secure Traffic', 'port' => '443', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '0.0.0.0/0']);
            FirewallRule::create(['name' => 'MySQL Database', 'port' => '3306', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '127.0.0.1']);
            FirewallRule::create(['name' => 'PostgreSQL Database', 'port' => '5432', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '127.0.0.1']);
            FirewallRule::create(['name' => 'Next.js App Default', 'port' => '3000', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '0.0.0.0/0']);
            FirewallRule::create(['name' => 'VPS Control Panel', 'port' => '8080', 'protocol' => 'tcp', 'action' => 'allow', 'from_ip' => '0.0.0.0/0']);
        }

        // 3. Seed Sample Databases (MySQL & PostgreSQL)
        $wpDb = Database::firstOrCreate(
            ['name' => 'wp_agency_db'],
            [
                'type' => 'mysql',
                'username' => 'wp_agency_user',
                'password' => 'WpSec#98124!Pass',
                'host' => '127.0.0.1',
                'port' => 3306,
                'size_mb' => 14.8,
            ]
        );

        $larDb = Database::firstOrCreate(
            ['name' => 'saas_laravel_prod'],
            [
                'type' => 'mysql',
                'username' => 'saas_admin',
                'password' => 'SaasSecure998$Key',
                'host' => '127.0.0.1',
                'port' => 3306,
                'size_mb' => 28.5,
            ]
        );

        $pgDb = Database::firstOrCreate(
            ['name' => 'nextjs_prisma_pg'],
            [
                'type' => 'postgres',
                'username' => 'postgres_user',
                'password' => 'PgSecure#8812Pass',
                'host' => '127.0.0.1',
                'port' => 5432,
                'size_mb' => 9.2,
            ]
        );

        // 4. Seed Sample Sites (Next.js, Laravel, WordPress, PHP)
        if (Site::count() === 0) {
            // Next.js App
            $nextSite = Site::create([
                'name' => 'E-Commerce Storefront',
                'domain' => 'storefront.nextapp.com',
                'aliases' => 'www.storefront.nextapp.com',
                'type' => 'nextjs',
                'status' => 'running',
                'node_version' => '22',
                'port' => 3000,
                'root_path' => '/var/www/storefront.nextapp.com',
                'web_directory' => '',
                'ssl_enabled' => true,
                'ssl_issued_at' => now()->subDays(10),
                'ssl_expires_at' => now()->addDays(80),
                'meta' => ['pm2_name' => 'app-storefront.nextapp.com'],
            ]);

            // WordPress Site
            $wpSite = Site::create([
                'name' => 'Digital Agency Blog',
                'domain' => 'agencyblog.org',
                'aliases' => 'www.agencyblog.org',
                'type' => 'wordpress',
                'status' => 'running',
                'php_version' => '8.3',
                'root_path' => '/var/www/agencyblog.org',
                'web_directory' => '',
                'database_id' => $wpDb->id,
                'ssl_enabled' => true,
                'ssl_issued_at' => now()->subDays(5),
                'ssl_expires_at' => now()->addDays(85),
                'meta' => ['wp_admin_user' => 'admin', 'wp_admin_email' => 'admin@agencyblog.org'],
            ]);

            // Laravel App
            $larSite = Site::create([
                'name' => 'SaaS API & Webhook Service',
                'domain' => 'api.cloudservice.io',
                'aliases' => null,
                'type' => 'laravel',
                'status' => 'running',
                'php_version' => '8.4',
                'root_path' => '/var/www/api.cloudservice.io',
                'web_directory' => '/public',
                'database_id' => $larDb->id,
                'ssl_enabled' => false,
                'git_repository' => 'https://github.com/myorg/saas-backend.git',
                'git_branch' => 'main',
            ]);

            // Custom PHP Site
            $phpSite = Site::create([
                'name' => 'Legacy Web Portal',
                'domain' => 'portal.internal.net',
                'aliases' => null,
                'type' => 'php',
                'status' => 'running',
                'php_version' => '8.3',
                'root_path' => '/var/www/portal.internal.net',
                'web_directory' => '',
                'ssl_enabled' => false,
            ]);

            // 5. Seed Task Logs
            TaskLog::create([
                'site_id' => $nextSite->id,
                'title' => 'Provisioned Next.js App on Port 3000',
                'type' => 'provision',
                'status' => 'completed',
                'output' => "[✓] Node.js 22 runtime configured\n[✓] PM2 ecosystem cluster configured\n[✓] Nginx WebSocket reverse proxy applied",
                'execution_time_ms' => 1240,
            ]);

            TaskLog::create([
                'site_id' => $wpSite->id,
                'title' => "Issued Let's Encrypt SSL for agencyblog.org",
                'type' => 'ssl',
                'status' => 'completed',
                'output' => "Certbot 2.8.0 verified challenges successfully.\nCertificate installed at /etc/letsencrypt/live/agencyblog.org/fullchain.pem",
                'execution_time_ms' => 2850,
            ]);
        }
    }
}
