<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnsZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'domain_name',
        'soa_email',
        'default_ttl',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'default_ttl' => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }
}
