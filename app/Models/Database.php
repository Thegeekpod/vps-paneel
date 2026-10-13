<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Database extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'name',
        'charset',
        'collation',
        'size_mb',
    ];

    protected function casts(): array
    {
        return [
            'size_mb' => 'decimal:2',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(DatabaseUser::class, 'database_user_assignments')
            ->withPivot('privileges')
            ->withTimestamps();
    }
}
