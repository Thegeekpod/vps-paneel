<?php

namespace App\Jobs;

use App\Models\BackupSnapshot;
use App\Services\BackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public BackupSnapshot $snapshot,
        public string $target = '/'
    ) {}

    public function handle(BackupManager $backupManager): void
    {
        $backupManager->restoreSnapshot($this->snapshot, $this->target);
    }
}
