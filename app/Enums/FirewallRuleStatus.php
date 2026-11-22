<?php

namespace App\Enums;

enum FirewallRuleStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
}
