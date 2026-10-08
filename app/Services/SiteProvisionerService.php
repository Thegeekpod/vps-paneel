<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Database;
use App\Models\TaskLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SiteProvisionerService
{
    public function __construct(
        protected NginxService $nginxService,
        protected DatabaseService $databaseService
    ) {}

    /**
     * Complete provisioning pipeline for a site.
     */
    public function provision(array $data): Site
    {
        $startTime = microtime(true);
        $logOutput = [];

        // 1. Calculate paths and defaults
        $domain = strtolower(trim($data['domain']));
        $type = $data['type']; // nextjs, laravel, wordpress, php
        $defaultRoot = PHP_OS_FAMILY === 'Linux' ? "/var/www/{$domain}" : storage_path("app/sites/{$domain}");
        $rootPath = $data['root_path'] ?? $defaultRoot;

        $webDir = match ($type) {
            'laravel' => '/public',
            'nextjs' => '',
            default => '',
        };

        // For Next.js, allocate internal port (e.g. 3000, 3001...)
        $port = null;
        if ($type === 'nextjs') {
            $lastPort = Site::where('type', 'nextjs')->max('port') ?: 2999;
            $port = $data['port'] ?? ($lastPort + 1);
        }

        // 2. Create directory structure
        File::ensureDirectoryExists($rootPath);
        $logOutput[] = "[✓] Created root directory: {$rootPath}";

        $database = null;

        // 3. Database provisioning for WordPress or requested
        if ($type === 'wordpress' || !empty($data['create_database'])) {
            $dbName = 'db_' . Str::slug(explode('.', $domain)[0], '_') . '_' . Str::random(4);
            $dbUser = 'usr_' . Str::random(6);
            $dbPass = Str::password(16);

            $database = $this->databaseService->createDatabase([
                'name' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
            ]);
            $logOutput[] = "[✓] Automatically created MySQL Database: {$dbName} (User: {$dbUser})";
        }

        // 4. Create Site Record in Database
        $site = Site::create([
            'name' => $data['name'] ?? ucfirst(explode('.', $domain)[0]),
            'domain' => $domain,
            'aliases' => $data['aliases'] ?? null,
            'type' => $type,
            'status' => 'running',
            'php_version' => $data['php_version'] ?? '8.3',
            'node_version' => $data['node_version'] ?? '22',
            'port' => $port,
            'root_path' => $rootPath,
            'web_directory' => $webDir,
            'database_id' => $database?->id,
            'git_repository' => $data['git_repository'] ?? null,
            'git_branch' => $data['git_branch'] ?? 'main',
            'ssl_enabled' => false,
            'meta' => [
                'pm2_name' => $type === 'nextjs' ? "app-{$domain}" : null,
                'wp_admin_user' => $data['wp_admin_user'] ?? 'admin',
                'wp_admin_email' => $data['wp_admin_email'] ?? "admin@{$domain}",
            ],
        ]);

        // 5. Setup Type-Specific Application Files
        match ($type) {
            'nextjs' => $this->setupNextJsApp($site, $logOutput),
            'wordpress' => $this->setupWordPressApp($site, $database, $logOutput),
            'laravel' => $this->setupLaravelApp($site, $database, $logOutput),
            'php' => $this->setupCustomPhpApp($site, $logOutput),
        };

        // 6. Generate and deploy Nginx Virtual Host
        $vhostResult = $this->nginxService->deployVhost($site);
        $logOutput[] = "[✓] Nginx Virtual Host configured: " . $vhostResult['message'];

        // 7. Save Execution Log
        $durationMs = round((microtime(true) - $startTime) * 1000);
        TaskLog::create([
            'site_id' => $site->id,
            'title' => "Site Provisioning: {$site->domain} ({$site->type})",
            'type' => 'provision',
            'status' => 'completed',
            'output' => implode("\n", $logOutput),
            'execution_time_ms' => $durationMs,
        ]);

        return $site;
    }

    private function setupNextJsApp(Site $site, array &$log): void
    {
        $root = $site->root_path;
        $port = $site->port;
        $pm2Name = "app-{$site->domain}";

        // Create package.json if empty
        if (!file_exists("{$root}/package.json")) {
            $packageJson = json_encode([
                'name' => Str::slug($site->domain),
                'version' => '0.1.0',
                'private' => true,
                'scripts' => [
                    'dev' => "next dev -p {$port}",
                    'build' => 'next build',
                    'start' => "next start -p {$port}",
                ],
                'dependencies' => [
                    'react' => '^19.0.0',
                    'react-dom' => '^19.0.0',
                    'next' => '^15.1.0',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            File::put("{$root}/package.json", $packageJson);
        }

        // PM2 Ecosystem config
        $ecosystem = <<<JS
module.exports = {
  apps: [{
    name: '{$pm2Name}',
    script: 'node_modules/next/dist/bin/next',
    args: 'start -p {$port}',
    cwd: '{$root}',
    instances: 'max',
    exec_mode: 'cluster',
    env: {
      PORT: {$port},
      NODE_ENV: 'production'
    }
  }]
};
JS;
        File::put("{$root}/ecosystem.config.cjs", $ecosystem);

        // Sample Welcome page
        File::ensureDirectoryExists("{$root}/app");
        $pageContent = <<<TSX
export default function Home() {
  return (
    <main style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily: 'sans-serif', background: '#090d16', color: '#fff' }}>
      <h1 style={{ fontSize: '2.5rem', fontWeight: 'bold', marginBottom: '1rem' }}>Welcome to {$site->domain}</h1>
      <p style={{ color: '#94a3b8' }}>Managed by VPS Panel • Next.js App running on port {$port}</p>
    </main>
  );
}
TSX;
        File::put("{$root}/app/page.tsx", $pageContent);

        $log[] = "[✓] Created Next.js package structure and ecosystem.config.cjs (PM2 cluster configured on port {$port})";
    }

    private function setupWordPressApp(Site $site, ?Database $db, array &$log): void
    {
        $root = $site->root_path;

        // Create wp-config.php
        $dbName = $db?->name ?? 'wordpress_db';
        $dbUser = $db?->username ?? 'root';
        $dbPass = $db?->password ?? '';
        $dbHost = $db?->host ?? '127.0.0.1';

        $salts = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
            $salts .= "define('{$key}', '" . Str::random(64) . "');\n";
        }

        $wpConfig = <<<PHP
<?php
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASSWORD', '{$dbPass}');
define('DB_HOST', '{$dbHost}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

{$salts}

\$table_prefix = 'wp_';
define('WP_DEBUG', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
PHP;

        File::put("{$root}/wp-config.php", $wpConfig);

        // Boilerplate index.php for instant preview before complete wp-core extract
        $indexPhp = <<<PHP
<?php
/**
 * WordPress Core Loader - Installed by VPS Panel
 */
define('WP_USE_THEMES', true);
if (file_exists(__DIR__ . '/wp-blog-header.php')) {
    require __DIR__ . '/wp-blog-header.php';
} else {
    echo '<!DOCTYPE html><html><head><title>WordPress Ready</title><style>body{background:#0f172a;color:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#1e293b;padding:2.5rem;border-radius:1rem;border:1px solid #334155;text-align:center;max-width:500px;}</style></head><body><div class="box"><h2>WordPress Environment Ready</h2><p style="color:#94a3b8">Domain: {$site->domain}</p><p style="color:#38bdf8">Database: {$dbName}</p><p style="font-size:0.9rem;color:#64748b;">WordPress core files & database are connected. Ready for installation!</p></div></body></html>';
}
PHP;
        File::put("{$root}/index.php", $indexPhp);

        $log[] = "[✓] WordPress wp-config.php created with isolated database credentials and security salts.";
    }

    private function setupLaravelApp(Site $site, ?Database $db, array &$log): void
    {
        $root = $site->root_path;
        $publicDir = "{$root}/public";
        File::ensureDirectoryExists($publicDir);

        // Create standard Laravel public/index.php
        $indexContent = <<<PHP
<?php
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
    \$app = require_once __DIR__.'/../bootstrap/app.php';
    \$app->handleRequest(Request::capture());
} else {
    echo '<!DOCTYPE html><html><head><title>Laravel Ready</title><style>body{background:#090d16;color:#f8fafc;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#111827;padding:2.5rem;border-radius:1rem;border:1px solid #1f2937;text-align:center;}</style></head><body><div class="box"><h2 style="color:#f43f5e">Laravel Application Ready</h2><p style="color:#94a3b8">Domain: {$site->domain}</p><p style="font-size:0.875rem;color:#6b7280">Git repository or composer files ready for deployment.</p></div></body></html>';
}
PHP;
        File::put("{$publicDir}/index.php", $indexContent);

        // .env file
        $dbName = $db?->name ?? 'forge';
        $dbUser = $db?->username ?? 'forge';
        $dbPass = $db?->password ?? '';
        $env = <<<ENV
APP_NAME="{$site->name}"
APP_ENV=production
APP_KEY=base64:
APP_DEBUG=false
APP_URL=http://{$site->domain}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD="{$dbPass}"
ENV;
        File::put("{$root}/.env", $env);

        $log[] = "[✓] Laravel structure initialized with public directory, index.php, and database .env";
    }

    private function setupCustomPhpApp(Site $site, array &$log): void
    {
        $root = $site->root_path;
        $indexContent = <<<PHP
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$site->domain} - PHP Application</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b0f19; color: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #131b2e; border: 1px solid #1e293b; padding: 2.5rem; border-radius: 1rem; text-align: center; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        h1 { color: #818cf8; font-size: 1.75rem; margin-top: 0; }
        .badge { background: #312e81; color: #c7d2fe; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">PHP <?= phpversion() ?></span>
        <h1>{$site->domain}</h1>
        <p>Your PHP website is running smoothly on this VPS server with Nginx and PHP-FPM.</p>
    </div>
</body>
</html>
PHP;
        File::put("{$root}/index.php", $indexContent);
        $log[] = "[✓] Created custom PHP template index.php";
    }

    /**
     * Delete site files, vhost, and record.
     */
    public function deleteSite(Site $site): void
    {
        // 1. Remove Nginx vhost
        $this->nginxService->removeVhost($site);

        // 2. Stop PM2 process if Next.js
        if ($site->type === 'nextjs' && !empty($site->meta['pm2_name'])) {
            $name = $site->meta['pm2_name'];
            @shell_exec("pm2 delete {$name} 2>&1");
        }

        // 3. Delete directory if exists
        if (File::isDirectory($site->root_path) && !str_contains($site->root_path, '/etc') && !str_contains($site->root_path, '/root')) {
            File::deleteDirectory($site->root_path);
        }

        $site->delete();
    }
}
