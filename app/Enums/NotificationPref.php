<?php

namespace App\Enums;

enum NotificationPref: string
{
    case All = 'all';
    case Mentions = 'mentions';
    case None = 'none';
}
