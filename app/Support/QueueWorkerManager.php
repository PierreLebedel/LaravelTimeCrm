<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class QueueWorkerManager
{
    public function ensureRunning(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $lock = Cache::lock('queue:worker:launch', 5);

        if (! $lock->get()) {
            return;
        }

        try {
            if ($this->wasStartedRecently()) {
                return;
            }

            $process = new Process([
                PHP_BINARY,
                'artisan',
                'queue:work',
                'database',
                '--queue=default',
                '--stop-when-empty',
                '--sleep=1',
                '--tries=1',
                '--max-time=60',
                '--no-ansi',
            ], base_path());

            $process->disableOutput();
            $process->setTimeout(null);

            if (DIRECTORY_SEPARATOR === '\\') {
                $process->setOptions([
                    'create_new_console' => true,
                ]);
            }

            $process->start();

            Cache::put('queue:worker:last-started-at', now()->timestamp, now()->addMinutes(5));
        } finally {
            $lock->release();
        }
    }

    protected function wasStartedRecently(): bool
    {
        $lastStartedAt = Cache::get('queue:worker:last-started-at');

        if (! is_int($lastStartedAt)) {
            return false;
        }

        return now()->diffInSeconds(now()->setTimestamp($lastStartedAt)) < 2;
    }
}
