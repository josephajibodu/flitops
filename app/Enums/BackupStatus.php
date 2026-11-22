<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Dumping = 'dumping';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Failed = 'failed';
}
