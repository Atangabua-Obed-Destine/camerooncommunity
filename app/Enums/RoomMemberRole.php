<?php

namespace App\Enums;

enum RoomMemberRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';
}
