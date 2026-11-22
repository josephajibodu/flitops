<?php

namespace App\Enums;

enum WorkerStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Stopped = 'stopped';
    case Crashed = 'crashed';
    case Starting = 'starting';
    case Stopping = 'stopping';
    case Restarting = 'restarting';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
