<?php

namespace App\Enums;

enum SslProvider: string
{
    case LetsEncrypt = 'letsencrypt';
    case Custom = 'custom';
    case SelfSigned = 'self-signed';
}
