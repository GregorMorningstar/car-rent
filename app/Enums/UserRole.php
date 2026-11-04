<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case User = 'user';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Name of the route the user lands on after logging in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Administrator => 'admin.dashboard',
            self::User => 'home',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::User => 'Użytkownik',
        };
    }
}
