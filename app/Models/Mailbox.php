<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mailbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_domain_id',
        'username',
        'email',
        'password_hash',
        'quota_mb',
        'used_mb',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'quota_mb' => 'integer',
            'used_mb' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function emailDomain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class);
    }
}
