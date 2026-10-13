<?php

namespace App\Services;

use App\Models\BackupJob;
use App\Models\BackupRepository;
use App\Models\BackupSnapshot;
use Carbon\Carbon;

class BackupManager
{
    public function __construct(
        protected ServerAgentService $agent
    ) {}

    /**
     * Run a backup job using Restic.
     */
    public function runJob(BackupJob $job): BackupSnapshot
    {
        $website = $job->website;
        $domain = $website ? $website->primary_domain : 'system';
        $paths = [];

        if ($job->include_files && $website) {
            $paths[] = $website->document_root;
        }

        $repoPath = $job->repository->path_or_uri;

        $result = $this->agent->execute('CreateBackup', [
            'domain' => $domain,
            'paths' => $paths,
            'repo_path' => $repoPath,
            'include_databases' => $job->include_databases,
        ], 'BackupJob', (string) $job->id);

        $snapshot = $job->snapshots()->create([
            'snapshot_id' => $result['snapshot_id'] ?? substr(hash('sha256', (string) time()), 0, 10),
            'paths' => $result['paths'] ?? $paths,
            'size_bytes' => $result['size_bytes'] ?? 0,
            'status' => 'completed',
        ]);

        $job->update(['last_run_at' => Carbon::now()]);

        return $snapshot;
    }

    /**
     * Restore a snapshot.
     */
    public function restoreSnapshot(BackupSnapshot $snapshot, string $target = '/'): array
    {
        return $this->agent->execute('RestoreBackup', [
            'snapshot_id' => $snapshot->snapshot_id,
            'target' => $target,
        ], 'BackupSnapshot', (string) $snapshot->id);
    }
}
