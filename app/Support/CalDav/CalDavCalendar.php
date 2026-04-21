<?php

namespace App\Support\CalDav;

final readonly class CalDavCalendar
{
    public function __construct(
        public string $externalId,
        public string $url,
        public string $name,
        public ?string $color,
        public ?string $timezone,
    ) {
    }
}
