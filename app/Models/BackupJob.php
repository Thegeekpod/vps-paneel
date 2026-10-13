<?php

namespace App\Models;

use App\Enums\BackupFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'backup_repository_id',
        'website_id',
        'name',
        'frequency',
        'include_files',
        'include_databases',
        'retention_daily',
        'retention_weekly',
        'retention_monthly',
        'status',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => BackupFrequency::class,
            'include_files' => 'boolean',
            'include_databases' => 'boolean',
            'retention_daily' => 'integer',
            'retention_weekly' => 'integer',
            'retention_monthly' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(BackupRepository::class, 'backup_repository_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BackupSnapshot::class);
    }
}
