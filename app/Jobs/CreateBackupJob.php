<?php

namespace App\Jobs;

use App\Models\BackupJob;
use App\Services\BackupManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public BackupJob $job
    ) {}

    public function handle(BackupManager $backupManager): void
    {
        $backupManager->runJob($this->job);
    }
}
