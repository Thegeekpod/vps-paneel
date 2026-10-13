<?php

namespace App\Models;

use App\Enums\SslProvider;
use App\Enums\SslStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'domain_name',
        'provider',
        'certificate_path',
        'private_key_path',
        'fullchain_path',
        'issuer',
        'expires_at',
        'auto_renew',
        'status',
        'last_renewed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'provider' => SslProvider::class,
            'status' => SslStatus::class,
            'expires_at' => 'datetime',
            'last_renewed_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function isExpiringSoon(int $daysThreshold = 14): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(Carbon::now()->addDays($daysThreshold));
    }
}
