<?php

namespace Extensions\Modules\Marketplace\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Developer = 'developer';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Developer => 'Developer',
            self::Support => 'Support',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 40,
            self::Manager => 30,
            self::Developer => 20,
            self::Support => 10,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }
}
