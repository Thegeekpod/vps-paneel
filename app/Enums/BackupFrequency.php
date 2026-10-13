<?php

namespace App\Enums;

enum BackupFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Manual = 'manual';
}
