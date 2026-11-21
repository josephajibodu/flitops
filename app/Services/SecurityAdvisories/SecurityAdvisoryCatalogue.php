<?php

namespace App\Services\SecurityAdvisories;

use InvalidArgumentException;

/**
 * Every security advisory Flitops has published, newest first.
 */
class SecurityAdvisoryCatalogue
{
    /**
     * @var list<class-string<SecurityAdvisory>>
     */
    private const ADVISORIES = [
        PhpAfterShellAdvisory::class,
        Nginx2RootAdvisory::class,
    ];

    /**
     * @return list<SecurityAdvisory>
     */
    public function all(): array
    {
        return array_map(fn (string $class): SecurityAdvisory => app($class), self::ADVISORIES);
    }

    public function has(string $key): bool
    {
        return collect($this->all())->contains(fn (SecurityAdvisory $advisory): bool => $advisory->key() === $key);
    }

    public function find(string $key): SecurityAdvisory
    {
        return collect($this->all())->first(fn (SecurityAdvisory $advisory): bool => $advisory->key() === $key)
            ?? throw new InvalidArgumentException("No security advisory [{$key}].");
    }
}
