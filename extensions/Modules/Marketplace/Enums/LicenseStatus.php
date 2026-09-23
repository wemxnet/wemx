<?php

namespace Extensions\Modules\Marketplace\Enums;

enum LicenseStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }
}
