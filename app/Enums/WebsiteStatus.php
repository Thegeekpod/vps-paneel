<?php

namespace App\Enums;

enum WebsiteStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Provisioning = 'provisioning';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Provisioning => 'info',
            self::Failed => 'danger',
        };
    }
}
