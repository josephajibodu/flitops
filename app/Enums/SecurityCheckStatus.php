<?php

namespace App\Enums;

/**
 * Where one server stands on one security advisory.
 */
enum SecurityCheckStatus: string
{
    /** Published, but nobody has checked this server yet. */
    case Unchecked = 'unchecked';

    case Checking = 'checking';

    /** The server already has the fix running. */
    case Covered = 'covered';

    /** The fixed software isn't installed yet. */
    case NeedsFix = 'needs_fix';

    /** The fixed software is installed, but the old copy is still running. */
    case NeedsRestart = 'needs_restart';

    /** The server's operating system no longer gets this fix. */
    case Unsupported = 'unsupported';

    case Fixing = 'fixing';

    /** We couldn't reach or read the server, so we don't know yet. */
    case CheckFailed = 'check_failed';

    case FixFailed = 'fix_failed';

    public function label(): string
    {
        return match ($this) {
            self::Unchecked => 'Not checked',
            self::Checking => 'Checking',
            self::Covered => 'Protected',
            self::NeedsFix => 'Needs update',
            self::NeedsRestart => 'Needs restart',
            self::Unsupported => 'Can\'t be patched',
            self::Fixing => 'Updating',
            self::CheckFailed => 'Check failed',
            self::FixFailed => 'Update failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unchecked => 'gray',
            self::Checking, self::Fixing => 'blue',
            self::Covered => 'green',
            self::NeedsFix, self::NeedsRestart => 'orange',
            self::Unsupported, self::CheckFailed, self::FixFailed => 'red',
        };
    }

    /**
     * A check or fix is already running, so another must wait.
     */
    public function isBusy(): bool
    {
        return in_array($this, [self::Checking, self::Fixing], true);
    }

    /**
     * Flitops can install the fix for the customer.
     */
    public function canBeFixed(): bool
    {
        return in_array($this, [self::NeedsFix, self::NeedsRestart, self::FixFailed], true);
    }
}
