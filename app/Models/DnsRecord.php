<?php

namespace App\Models;

use App\Enums\DnsRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'dns_zone_id',
        'name',
        'type',
        'content',
        'ttl',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'type' => DnsRecordType::class,
            'ttl' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }
}
