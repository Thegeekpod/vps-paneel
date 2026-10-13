<?php

namespace App\Console\Commands;

use App\Enums\BackupFrequency;
use App\Jobs\CreateBackupJob;
use App\Models\BackupJob;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    protected $signature = 'vps:backups-run';
    protected $description = 'Dispatch pending scheduled backups';

    public function handle(): int
    {
        $this->info('Checking scheduled backups...');

        $jobs = BackupJob::where('status', 'active')->get();
        $dispatched = 0;

        foreach ($jobs as $job) {
            $shouldRun = match ($job->frequency) {
                BackupFrequency::Daily => ! $job->last_run_at || $job->last_run_at->diffInHours(now()) >= 24,
                BackupFrequency::Weekly => ! $job->last_run_at || $job->last_run_at->diffInDays(now()) >= 7,
                BackupFrequency::Monthly => ! $job->last_run_at || $job->last_run_at->diffInDays(now()) >= 30,
                default => false,
            };

            if ($shouldRun) {
                $this->info("Dispatching backup job: {$job->name} (#{$job->id})");
                CreateBackupJob::dispatch($job);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} backup job(s).");
        return self::SUCCESS;
    }
}
