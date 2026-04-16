<?php

namespace App\Enums;

enum TenantPlan: string
{
    case Owned = 'owned';
    case Licensed = 'licensed';
}
