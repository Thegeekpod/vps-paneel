<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::Admin => 'Administrator',
            self::Operator => 'Operator (No User Admin)',
            self::Viewer => 'Read-Only Viewer',
        };
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function canPerformDestructiveActions(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    public function canExecuteServerOperations(): bool
    {
        return $this !== self::Viewer;
    }
}
