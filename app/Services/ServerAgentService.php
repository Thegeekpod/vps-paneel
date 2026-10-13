<?php

namespace App\Services;

use App\Enums\OperationStatus;
use App\Models\ServerOperation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ServerAgentService
{
    /**
     * Execute an allowlisted server operation via the privileged agent.
     *
     * @param string $action Allowlisted action name
     * @param array $payload Input parameters
     * @param string|null $targetType Eloquent model or resource type
     * @param string|null $targetId Target identifier (e.g. website ID or domain)
     * @return array Result data from agent
     * @throws \RuntimeException
     */
    public function execute(string $action, array $payload = [], ?string $targetType = null, ?string $targetId = null): array
    {
        $startTime = microtime(true);
        $user = Auth::user();
        $ip = request()?->ip() ?? '127.0.0.1';

        // Redact secrets before saving to audit log
        $sanitizedPayload = ServerOperation::redactSensitive($payload);

        $operation = ServerOperation::create([
            'user_id' => $user?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'status' => OperationStatus::Running,
            'payload' => $sanitizedPayload,
            'ip_address' => $ip,
        ]);

        try {
            $agentPath = config('vps.agent_path', base_path('bin/vps-agent'));
            $useSudo = config('vps.use_sudo', PHP_OS_FAMILY === 'Linux');
            $simulate = config('vps.simulate', PHP_OS_FAMILY !== 'Linux');

            $command = [];
            if ($useSudo) {
                $command[] = 'sudo';
            }
            $command[] = $agentPath;
            $command[] = "--action={$action}";
            $command[] = '--payload=' . json_encode($payload);

            if ($simulate) {
                $command[] = '--simulate';
            }

            $process = new Process($command);
            $process->setTimeout(120);
            $process->run();

            $output = $process->getOutput();
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $parsed = json_decode($output, true);

            if (! $parsed || ! ($parsed['success'] ?? false)) {
                $errorMessage = $parsed['error'] ?? $output ?: 'Server agent operation failed';
                $operation->update([
                    'status' => OperationStatus::Failed,
                    'error_message' => $errorMessage,
                    'result' => ServerOperation::redactSensitive($parsed['extra'] ?? []),
                    'duration_ms' => $durationMs,
                ]);

                Log::error("Server Agent Error [{$action}]: {$errorMessage}");
                throw new \RuntimeException("Server Agent Error: {$errorMessage}");
            }

            $sanitizedResult = ServerOperation::redactSensitive($parsed['data'] ?? []);

            $operation->update([
                'status' => OperationStatus::Completed,
                'result' => $sanitizedResult,
                'duration_ms' => $durationMs,
            ]);

            return $parsed['data'] ?? [];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $operation->update([
                'status' => OperationStatus::Failed,
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            throw $e;
        }
    }
}
