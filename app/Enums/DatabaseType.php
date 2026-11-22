<?php

namespace App\Enums;

/**
 * Database engines FlitOps can install on a managed server. Single source of
 * truth for the engine list, the MySQL-family grouping, the default client
 * port, display labels, and the matching PHP-FPM/service type.
 */
enum DatabaseType: string
{
    case Mysql = 'mysql';
    case Postgresql = 'postgresql';
    case Mariadb = 'mariadb';

    public function label(): string
    {
        return match ($this) {
            self::Mysql => 'MySQL',
            self::Postgresql => 'PostgreSQL',
            self::Mariadb => 'MariaDB',
        };
    }

    /**
     * MySQL and MariaDB share a wire protocol, client binaries, and SQL
     * dialect, so most engine-specific code treats them identically and only
     * PostgreSQL takes a different path.
     */
    public function isMysqlFamily(): bool
    {
        return $this === self::Mysql || $this === self::Mariadb;
    }

    /**
     * The TCP port the engine's client connects on by default.
     */
    public function port(): int
    {
        return $this === self::Postgresql ? 5432 : 3306;
    }

    /**
     * The managed service that runs this engine. MySQL and MariaDB are both
     * served by the MySQL service type.
     */
    public function serviceType(): ServiceType
    {
        return $this === self::Postgresql ? ServiceType::Postgresql : ServiceType::Mysql;
    }

    /**
     * The engine preselected when provisioning a new server.
     */
    public static function default(): self
    {
        return self::Mysql;
    }

    /**
     * Every supported engine value. Use for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /**
     * Options for an engine picker.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
