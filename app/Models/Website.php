<?php

namespace App\Models;

use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'primary_domain',
        'system_user',
        'php_version',
        'document_root',
        'status',
        'ssl_enabled',
        'disk_usage_mb',
        'bandwidth_usage_mb',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => WebsiteStatus::class,
            'ssl_enabled' => 'boolean',
            'disk_usage_mb' => 'integer',
            'bandwidth_usage_mb' => 'integer',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomainRelation(): HasOne
    {
        return $this->hasOne(Domain::class)->where('type', 'primary');
    }

    public function sslCertificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function backupJobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }

    public function dnsZone(): HasOne
    {
        return $this->hasOne(DnsZone::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === WebsiteStatus::Suspended;
    }

    public function isActive(): bool
    {
        return $this->status === WebsiteStatus::Active;
    }

    public function getHomeDirectoryAttribute(): string
    {
        return "/home/{$this->system_user}";
    }

    public function getWebDirectoryAttribute(): string
    {
        return "{$this->home_directory}/web/{$this->primary_domain}";
    }

    public function getLogsDirectoryAttribute(): string
    {
        return "{$this->web_directory}/logs";
    }

    public function getNginxVhostPathAttribute(): string
    {
        return "/etc/nginx/sites-available/{$this->primary_domain}.conf";
    }

    public function getFpmPoolPathAttribute(): string
    {
        return "/etc/php/{$this->php_version}/fpm/pool.d/{$this->system_user}.conf";
    }

    public function getFpmSocketPathAttribute(): string
    {
        return "/run/php/php{$this->php_version}-fpm-{$this->system_user}.sock";
    }
}
