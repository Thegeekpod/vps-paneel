<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_name',
        'status',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
    ];

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MailAlias::class);
    }
}
