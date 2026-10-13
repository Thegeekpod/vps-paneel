<?php

namespace App\Enums;

enum SslStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Pending = 'pending';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Expired => 'danger',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }
}
