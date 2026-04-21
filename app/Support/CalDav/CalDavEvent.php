<?php

namespace App\Support\CalDav;

use Carbon\CarbonImmutable;

final readonly class CalDavEvent
{
    public function __construct(
        public ?string $icalUid,
        public string $externalId,
        public ?string $etag,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $timezone,
        public string $title,
        public ?string $description,
        public ?CarbonImmutable $sourceUpdatedAt,
    ) {
    }
}
