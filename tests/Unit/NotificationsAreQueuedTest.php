<?php

use Illuminate\Contracts\Queue\ShouldQueue;

arch('notifications are queued so a slow or broken mail server never holds up a request or job')
    ->expect('App\Notifications')
    ->classes()
    ->toImplement(ShouldQueue::class);
