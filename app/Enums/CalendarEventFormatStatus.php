<?php

namespace App\Enums;

enum CalendarEventFormatStatus: string
{
    case Formatted = 'formatted';
    case NeedsReview = 'needs_review';
    case Ignored = 'ignored';
}
