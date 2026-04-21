<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('it renders the queue dashboard with pending running and failed jobs', function () {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\PushCalendarEventToRemoteJob',
        ]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => 'failed-job-1',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\SyncCalendarAccountJob',
        ]),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    Cache::forever('queue:running_jobs', [
        'running-job-1' => [
            'uuid' => 'running-job-1',
            'name' => 'App\\Jobs\\SyncCalendarAccountJob',
            'queue' => 'default',
            'started_at' => now()->toIso8601String(),
        ],
    ]);

    $this->get('/queue')
        ->assertOk()
        ->assertSee('Queue de jobs')
        ->assertSee('PushCalendarEventToRemoteJob')
        ->assertSee('SyncCalendarAccountJob');
});
