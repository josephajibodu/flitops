<?php

namespace App\Enums;

/**
 * Web servers FlitOps can install on a managed server. Single source of truth
 * for the engine list, display labels, and the matching service type. The
 * per-engine provisioning logic lives in the WebServerContract implementations
 * (NginxWebServer, CaddyWebServer), resolved via Server::webServer().
 */
enum WebServer: string
{
    case Nginx = 'nginx';
    case Caddy = 'caddy';

    public function label(): string
    {
        return match ($this) {
            self::Nginx => 'Nginx',
            self::Caddy => 'Caddy',
        };
    }

    /**
     * A short note shown next to the label in pickers, or null when the label
     * stands on its own.
     */
    public function description(): ?string
    {
        return match ($this) {
            self::Nginx => null,
            self::Caddy => 'Automatic HTTPS',
        };
    }

    /**
     * The managed service that runs this web server.
     */
    public function serviceType(): ServiceType
    {
        return match ($this) {
            self::Nginx => ServiceType::Nginx,
            self::Caddy => ServiceType::Caddy,
        };
    }

    /**
     * The web server preselected when provisioning a new server.
     */
    public static function default(): self
    {
        return self::Nginx;
    }

    /**
     * Every supported web server value. Use for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $server): string => $server->value, self::cases());
    }

    /**
     * Options for a web server picker.
     *
     * @return array<int, array{value: string, label: string, description: string|null}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $server): array => [
                'value' => $server->value,
                'label' => $server->label(),
                'description' => $server->description(),
            ],
            self::cases(),
        );
    }
}
