<?php

use App\Enums\PhpVersion;

describe('PhpVersion::siteFallback', function () {
    it('returns a real, provisionable case', function () {
        expect(PhpVersion::siteFallback())->toBeInstanceOf(PhpVersion::class)
            ->and(PhpVersion::values())->toContain(PhpVersion::siteFallback()->value);
    });

    it('is the installed-base version assumed for a site with no version of its own', function () {
        expect(PhpVersion::siteFallback())->toBe(PhpVersion::Php84);
    });
});
