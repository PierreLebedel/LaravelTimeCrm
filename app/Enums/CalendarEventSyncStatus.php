<?php

namespace App\Enums;

enum CalendarEventSyncStatus: string
{
    case Queued = 'queued';
    case Synced = 'synced';
    case Conflict = 'conflict';
    case Orphaned = 'orphaned';
}
