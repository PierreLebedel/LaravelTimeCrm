<?php

namespace App\Support\CalDav;

final readonly class CalDavSyncResult
{
    public function __construct(
        public int $calendarCount,
        public int $eventCount,
    ) {
    }
}
