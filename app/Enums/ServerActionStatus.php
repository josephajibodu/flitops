<?php

namespace App\Enums;

enum ServerActionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';
}
