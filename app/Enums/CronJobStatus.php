<?php

namespace App\Enums;

enum CronJobStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
