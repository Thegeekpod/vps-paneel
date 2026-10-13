<?php

namespace App\Services;

use App\Enums\OperationStatus;
use App\Models\ServerOperation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;

class PanelUpdateService
{
    /**
     * Get details of the currently deployed Git commit.
     */
    public function getCurrentVersion(): array
    {
        $base = base_path();

        $branch = $this->runCommand(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $base);
        $hash = $this->runCommand(['git', 'rev-parse', '--short', 'HEAD'], $base);
        $fullHash = $this->runCommand(['git', 'rev-parse', 'HEAD'], $base);
        $message = $this->runCommand(['git', 'log', '-1', '--pretty=%s'], $base);
        $date = $this->runCommand(['git', 'log', '-1', '--pretty=%cd', '--date=relative'], $base);

        return [
            'branch' => trim($branch['output']) ?: 'main',
            'hash' => trim($hash['output']) ?: 'v1.0.0',
            'full_hash' => trim($fullHash['output']) ?: 'v1.0.0',
            'message' => trim($message['output']) ?: 'Initial release',
            'date' => trim($date['output']) ?: 'recently',
        ];
    }

    /**
     * Check if origin has newer commits.
     */
    public function checkForUpdates(): array
    {
        $base = base_path();
        $version = $this->getCurrentVersion();
        $branch = $version['branch'];

        // Fetch remote references
        $fetch = $this->runCommand(['git', 'fetch', 'origin', $branch], $base);

        $behind = $this->runCommand(['git', 'rev-list', "--count", "HEAD..origin/{$branch}"], $base);
        $count = (int) trim($behind['output']);

        return [
            'is_behind' => $count > 0,
            'commits_behind' => $count,
            'current_hash' => $version['hash'],
            'branch' => $branch,
        ];
    }

    /**
     * Perform the one-click update: git pull, composer, migrate, assets, optimize, and restart workers.
     */
    public function update(): array
    {
        $startTime = microtime(true);
        $base = base_path();
        $user = Auth::user();
        $ip = request()?->ip() ?? '127.0.0.1';
        $versionBefore = $this->getCurrentVersion();
        $branch = $versionBefore['branch'];

        $logs = [];
        $logs[] = "Starting Panel Update process for branch: {$branch}";
        $logs[] = "Current commit: {$versionBefore['hash']} ({$versionBefore['message']})";

        // Step 1: Git Pull
        $pull = $this->runCommand(['git', 'pull', 'origin', $branch], $base);
        $logs[] = "[git pull]: " . ($pull['output'] ?: 'Up to date');

        // Step 2: Composer install (if vendor exists)
        if (file_exists($base . '/composer.lock')) {
            $composer = $this->runCommand(['composer', 'install', '--no-interaction', '--prefer-dist', '--optimize-autoloader', '--no-dev'], $base);
            $logs[] = "[composer]: " . (str_contains($composer['output'], 'Generating') ? 'Dependencies refreshed' : trim($composer['output']));
        }

        // Step 3: Artisan migrate --force
        try {
            Artisan::call('migrate', ['--force' => true]);
            $logs[] = "[artisan migrate]: " . trim(Artisan::output());
        } catch (\Throwable $e) {
            $logs[] = "[artisan migrate error]: " . $e->getMessage();
        }

        // Step 4: Publish Filament assets
        try {
            Artisan::call('filament:assets');
            $logs[] = "[filament assets]: Published assets";
        } catch (\Throwable $e) {
            $logs[] = "[filament assets error]: " . $e->getMessage();
        }

        // Step 5: Clear and optimize caches
        try {
            Artisan::call('optimize:clear');
            $logs[] = "[cache clear]: Compiled caches cleared";
        } catch (\Throwable $e) {
            $logs[] = "[cache clear error]: " . $e->getMessage();
        }

        // Step 6: Restart queue workers so new code takes effect immediately
        try {
            Artisan::call('queue:restart');
            $logs[] = "[queue]: Queue workers notified to restart";
        } catch (\Throwable $e) {
            $logs[] = "[queue error]: " . $e->getMessage();
        }

        $versionAfter = $this->getCurrentVersion();
        $logs[] = "New commit: {$versionAfter['hash']} ({$versionAfter['message']})";

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Record audit operation
        ServerOperation::create([
            'user_id' => $user?->id,
            'action' => 'UpdatePanel',
            'target_type' => 'System',
            'target_id' => $versionAfter['hash'],
            'status' => OperationStatus::Completed,
            'payload' => [
                'previous_hash' => $versionBefore['hash'],
                'branch' => $branch,
            ],
            'result' => [
                'new_hash' => $versionAfter['hash'],
                'logs' => $logs,
            ],
            'ip_address' => $ip,
            'duration_ms' => $durationMs,
        ]);

        return [
            'success' => true,
            'previous_hash' => $versionBefore['hash'],
            'new_hash' => $versionAfter['hash'],
            'logs' => $logs,
            'duration_ms' => $durationMs,
        ];
    }

    protected function runCommand(array $command, string $cwd): array
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(180);
        $process->run();

        return [
            'exitCode' => $process->getExitCode(),
            'output' => trim($process->getOutput() . "\n" . $process->getErrorOutput()),
        ];
    }
}
