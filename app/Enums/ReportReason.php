<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Scam = 'scam';
    case Misinformation = 'misinformation';
    case Inappropriate = 'inappropriate';
    case Other = 'other';
}
