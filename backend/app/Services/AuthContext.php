<?php

declare(strict_types=1);

namespace App\Services;

class AuthContext
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @var array<string, mixed>|null */
    private static ?array $activist = null;

    private static ?string $tokenHash = null;

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed>|null $activist
     */
    public static function set(?array $user, ?array $activist = null, ?string $tokenHash = null): void
    {
        self::$user      = $user;
        self::$activist  = $activist;
        self::$tokenHash = $tokenHash;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return self::$user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function activist(): ?array
    {
        return self::$activist;
    }

    public static function tokenHash(): ?string
    {
        return self::$tokenHash;
    }

    public static function clear(): void
    {
        self::$user      = null;
        self::$activist  = null;
        self::$tokenHash = null;
    }
}
