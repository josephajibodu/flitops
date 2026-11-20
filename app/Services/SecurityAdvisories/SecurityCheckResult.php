<?php

namespace App\Services\SecurityAdvisories;

use App\Enums\SecurityCheckStatus;

/**
 * What checking one server against an advisory found, with a plain sentence
 * the customer is shown.
 */
final readonly class SecurityCheckResult
{
    public function __construct(
        public SecurityCheckStatus $status,
        public string $detail,
    ) {}

    public static function covered(string $detail): self
    {
        return new self(SecurityCheckStatus::Covered, $detail);
    }

    public static function needsFix(string $detail): self
    {
        return new self(SecurityCheckStatus::NeedsFix, $detail);
    }

    public static function needsRestart(string $detail): self
    {
        return new self(SecurityCheckStatus::NeedsRestart, $detail);
    }

    public static function unsupported(string $detail): self
    {
        return new self(SecurityCheckStatus::Unsupported, $detail);
    }
}
