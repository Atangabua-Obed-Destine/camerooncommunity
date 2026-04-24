<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case System = 'system';
    case SolidarityCard = 'solidarity_card';
    case Gif = 'gif';
    case Sticker = 'sticker';
    case CallLog = 'call_log';
    case Poll = 'poll';
}
