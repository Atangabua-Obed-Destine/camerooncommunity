<?php

namespace App\Enums;

enum JoinPromptAction: string
{
    case Joined = 'joined';
    case Dismissed = 'dismissed';
    case Pending = 'pending';
}
