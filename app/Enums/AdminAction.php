<?php

namespace App\Enums;

/**
 * Something a Flitops staff member did in the admin panel.
 */
enum AdminAction: string
{
    case PublishedSecurityAdvisory = 'published_security_advisory';

    public function label(): string
    {
        return match ($this) {
            self::PublishedSecurityAdvisory => 'Published a security advisory',
        };
    }
}
