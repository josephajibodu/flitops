<?php

namespace App\Enums;

enum WorkerControlAction: string
{
    case Start = 'start';
    case Stop = 'stop';
    case Restart = 'restart';

    public function targetStatus(): WorkerStatus
    {
        return match ($this) {
            self::Start, self::Restart => WorkerStatus::Active,
            self::Stop => WorkerStatus::Stopped,
        };
    }

    public function pendingStatus(): WorkerStatus
    {
        return match ($this) {
            self::Start => WorkerStatus::Starting,
            self::Stop => WorkerStatus::Stopping,
            self::Restart => WorkerStatus::Restarting,
        };
    }
}
