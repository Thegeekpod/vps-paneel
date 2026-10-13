<?php

namespace App\Enums;

enum DomainType: string
{
    case Primary = 'primary';
    case Alias = 'alias';
    case Redirect = 'redirect';
}
