<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    protected $guarded = ['id'];

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->type) {
            'postgres' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            default => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
        };
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'postgres' => 'PostgreSQL',
            default => 'MySQL / MariaDB',
        };
    }
}
