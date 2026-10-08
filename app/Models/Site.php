<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'ssl_auto_renew' => 'boolean',
        'ssl_issued_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'auto_deploy' => 'boolean',
        'meta' => 'array',
    ];

    public function database()
    {
        return $this->belongsTo(Database::class);
    }

    public function taskLogs()
    {
        return $this->hasMany(TaskLog::class)->latest();
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->type) {
            'nextjs' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
            'laravel' => 'bg-red-500/10 text-red-400 border-red-500/20',
            'wordpress' => 'bg-sky-500/10 text-sky-400 border-sky-500/20',
            'php' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
            default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'running' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
            'deploying' => 'bg-amber-500/10 text-amber-400 border-amber-500/20 animate-pulse',
            'stopped' => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
            'error' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
            default => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
        };
    }
}
