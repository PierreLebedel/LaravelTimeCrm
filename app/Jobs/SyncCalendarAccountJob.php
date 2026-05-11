<?php

namespace App\Jobs;

use App\Models\CalendarAccount;
use App\Support\CalendarAccountSynchronizer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[DeleteWhenMissingModels]
#[Tries(3)]
#[Timeout(300)]
#[Backoff(20, 40, 60)]
class SyncCalendarAccountJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $calendarAccountId,
    ) {
        $this->onConnection(config('queue.default'));
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'calendar-account:'.$this->calendarAccountId;
    }

    public function handle(CalendarAccountSynchronizer $synchronizer): void
    {
        $account = CalendarAccount::query()->findOrFail($this->calendarAccountId);

        $synchronizer->sync($account);
    }
}
