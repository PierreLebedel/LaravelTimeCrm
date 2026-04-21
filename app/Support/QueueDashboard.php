<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueDashboard
{
    /**
     * @return array{
     *     pending_count: int,
     *     reserved_count: int,
     *     failed_count: int,
     *     running_count: int
     * }
     */
    public function summary(): array
    {
        return [
            'pending_count' => DB::table('jobs')->whereNull('reserved_at')->count(),
            'reserved_count' => DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed_count' => DB::table('failed_jobs')->count(),
            'running_count' => collect(Cache::get('queue:running_jobs', []))->count(),
        ];
    }

    public function pendingJobs(): Collection
    {
        return DB::table('jobs')
            ->orderByRaw('reserved_at is not null desc')
            ->orderBy('available_at')
            ->limit(50)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id,
                'name' => $this->jobDisplayName($job->payload),
                'queue' => $job->queue,
                'reserved' => $job->reserved_at !== null,
                'available_at' => CarbonImmutable::createFromTimestamp((int) $job->available_at)->format('d/m/Y H:i:s'),
                'attempts' => $job->attempts,
            ]);
    }

    public function runningJobs(): Collection
    {
        return collect(Cache::get('queue:running_jobs', []))
            ->values()
            ->map(fn (array $job): array => [
                'uuid' => $job['uuid'],
                'name' => $job['name'],
                'queue' => $job['queue'],
                'started_at' => CarbonImmutable::parse($job['started_at'])->format('d/m/Y H:i:s'),
            ]);
    }

    public function failedJobs(): Collection
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(25)
            ->get()
            ->map(fn (object $job): array => [
                'uuid' => $job->uuid,
                'name' => $this->jobDisplayName($job->payload),
                'queue' => $job->queue,
                'failed_at' => CarbonImmutable::parse($job->failed_at)->format('d/m/Y H:i:s'),
                'exception' => str($job->exception)->explode("\n")->first(),
            ]);
    }

    protected function jobDisplayName(string $payload): string
    {
        $decodedPayload = json_decode($payload, true);

        if (! is_array($decodedPayload)) {
            return 'Job inconnu';
        }

        return (string) ($decodedPayload['displayName'] ?? $decodedPayload['job'] ?? 'Job inconnu');
    }
}
