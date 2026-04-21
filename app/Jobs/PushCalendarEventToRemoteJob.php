<?php

namespace App\Jobs;

use App\Enums\CalendarEventSyncStatus;
use App\Models\CalendarEvent;
use App\Support\CalDav\CalDavClient;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Throwable;

#[DeleteWhenMissingModels]
class PushCalendarEventToRemoteJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $calendarEventId,
    ) {
        $this->onConnection(config('queue.default'));
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'calendar-event:' . $this->calendarEventId;
    }

    public function handle(CalDavClient $client): void
    {
        $event = CalendarEvent::query()
            ->with(['calendar.account', 'client', 'project'])
            ->findOrFail($this->calendarEventId);

        $etag = $client->updateEvent($event);

        $event->forceFill([
            'external_etag' => $etag ?? $event->external_etag,
            'sync_status' => CalendarEventSyncStatus::Synced,
            'last_synced_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $event = CalendarEvent::query()->find($this->calendarEventId);

        if ($event === null) {
            return;
        }

        $event->forceFill([
            'sync_status' => CalendarEventSyncStatus::Conflict,
        ])->save();
    }
}
