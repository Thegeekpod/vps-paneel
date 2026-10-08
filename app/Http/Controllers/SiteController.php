<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Models\Site;
use App\Models\TaskLog;
use App\Services\NginxService;
use App\Services\SiteProvisionerService;
use App\Services\SslCertbotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __construct(
        protected SiteProvisionerService $provisionerService,
        protected NginxService $nginxService,
        protected SslCertbotService $sslService
    ) {}

    public function index(Request $request): View
    {
        $query = Site::query();

        if ($request->filled('type') && in_array($request->type, ['nextjs', 'laravel', 'wordpress', 'php'])) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        $sites = $query->latest()->paginate(12)->withQueryString();

        return view('sites.index', compact('sites'));
    }

    public function create(): View
    {
        $databases = Database::all();
        return view('sites.create', compact('databases'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'domain' => 'required|string|max:150|unique:sites,domain',
            'aliases' => 'nullable|string|max:255',
            'type' => 'required|in:nextjs,laravel,wordpress,php',
            'php_version' => 'nullable|in:8.2,8.3,8.4',
            'node_version' => 'nullable|in:20,22',
            'port' => 'nullable|integer|min:1024|max:65535',
            'git_repository' => 'nullable|string|max:255',
            'git_branch' => 'nullable|string|max:100',
            'create_database' => 'nullable|boolean',
            'wp_admin_user' => 'nullable|string|max:50',
            'wp_admin_email' => 'nullable|email|max:100',
        ]);

        $site = $this->provisionerService->provision($validated);

        return redirect()->route('sites.show', $site)->with('success', "Site {$site->domain} ({$site->type}) created and provisioned successfully!");
    }

    public function show(Site $site): View
    {
        $nginxConfig = $this->nginxService->generateConfig($site);
        $taskLogs = $site->taskLogs()->take(10)->get();

        // Read .env if Laravel or Next.js
        $envContent = '';
        $envPath = "{$site->root_path}/.env";
        if (file_exists($envPath)) {
            $envContent = File::get($envPath);
        }

        return view('sites.show', compact('site', 'nginxConfig', 'taskLogs', 'envContent'));
    }

    public function toggleSsl(Request $request, Site $site): RedirectResponse
    {
        if ($site->ssl_enabled) {
            $this->sslService->disableCertificate($site);
            return back()->with('info', "SSL has been disabled for {$site->domain}.");
        }

        $result = $this->sslService->issueCertificate($site, $request->input('email'));

        if ($result['success']) {
            return back()->with('success', "Let's Encrypt SSL certificate successfully installed for {$site->domain}!");
        }

        return back()->with('error', "Failed to issue SSL: " . $result['output']);
    }

    public function restart(Site $site): RedirectResponse
    {
        $startTime = microtime(true);
        $output = '';

        if ($site->type === 'nextjs') {
            $pm2Name = $site->meta['pm2_name'] ?? "app-{$site->domain}";
            if (PHP_OS_FAMILY === 'Linux') {
                $output = @shell_exec("pm2 restart {$pm2Name} 2>&1");
            } else {
                $output = "PM2 service '{$pm2Name}' restarted (Simulator).";
            }
        } else {
            // PHP/WordPress/Laravel
            if (PHP_OS_FAMILY === 'Linux') {
                $phpVer = $site->php_version ?: '8.3';
                $output = @shell_exec("systemctl reload php{$phpVer}-fpm 2>&1 && systemctl reload nginx 2>&1");
            } else {
                $output = "Nginx and PHP-FPM reloaded successfully.";
            }
        }

        TaskLog::create([
            'site_id' => $site->id,
            'title' => "Restarted application: {$site->domain}",
            'type' => 'restart',
            'status' => 'completed',
            'output' => $output ?: 'Process reloaded successfully.',
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000),
        ]);

        return back()->with('success', "Site {$site->domain} service reloaded successfully.");
    }

    public function saveEnv(Request $request, Site $site): RedirectResponse
    {
        $content = $request->input('env_content', '');
        $envPath = "{$site->root_path}/.env";

        File::put($envPath, $content);
        $site->update(['env_content' => $content]);

        return back()->with('success', "Environment variables saved successfully.");
    }

    public function destroy(Site $site): RedirectResponse
    {
        $domain = $site->domain;
        $this->provisionerService->deleteSite($site);

        return redirect()->route('sites.index')->with('success', "Site {$domain} deleted successfully.");
    }
}
