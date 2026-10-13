<?php

namespace App\Models;

use App\Enums\DomainType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'domain_name',
        'is_subdomain',
        'type',
        'redirect_url',
        'redirect_status',
        'ssl_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_subdomain' => 'boolean',
            'type' => DomainType::class,
            'redirect_status' => 'integer',
            'ssl_enabled' => 'boolean',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
