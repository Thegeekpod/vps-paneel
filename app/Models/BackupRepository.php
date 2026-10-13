<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRepository extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'path_or_uri',
        'credentials_json',
        'is_initialized',
    ];

    protected $casts = [
        'is_initialized' => 'boolean',
        'credentials_json' => 'encrypted:array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }
}
