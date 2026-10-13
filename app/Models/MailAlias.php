<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_domain_id',
        'source_email',
        'destination_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function emailDomain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class);
    }
}
