<?php

namespace App\Enums;

/**
 * Status of a server database or a database user.
 */
enum DatabaseStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
