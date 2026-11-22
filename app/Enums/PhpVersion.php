<?php

namespace App\Enums;

/**
 * Every PHP version FlitOps can install on a managed server. This is the
 * single source of truth for the provisionable version list: validation rules
 * and the server/site pickers all read from here.
 */
enum PhpVersion: string
{
    case Php81 = '8.1';
    case Php82 = '8.2';
    case Php83 = '8.3';
    case Php84 = '8.4';
    case Php85 = '8.5';
    case Php86 = '8.6';

    public function label(): string
    {
        return "PHP {$this->value}";
    }

    /**
     * The version preselected when provisioning a new server or site. Kept to
     * the latest well-established release rather than the newest case, so a
     * freshly released version is opt-in until its ecosystem catches up.
     */
    public static function default(): self
    {
        return self::Php85;
    }

    /**
     * Every supported version number, oldest first. Use for validation rules
     * where ordering does not matter.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $version): string => $version->value, self::cases());
    }

    /**
     * Options for a version picker, newest first.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $version): array => [
                'value' => $version->value,
                'label' => $version->label(),
            ],
            array_reverse(self::cases()),
        );
    }
}
