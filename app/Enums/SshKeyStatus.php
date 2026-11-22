<?php

namespace App\Enums;

/**
 * Status of one SSH key placed on one Linux user of a server.
 */
enum SshKeyStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Revoking = 'revoking';
    case Failed = 'failed';
}
