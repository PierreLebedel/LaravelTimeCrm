<?php

namespace App\Models;

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'calendar_id',
    'client_id',
    'project_id',
    'ical_uid',
    'external_id',
    'external_etag',
    'starts_at',
    'ends_at',
    'timezone',
    'title',
    'description',
    'feature_description',
    'sync_status',
    'format_status',
    'source_updated_at',
    'last_synced_at',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarEventFactory> */
    use HasFactory;

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_status' => CalendarEventSyncStatus::class,
            'format_status' => CalendarEventFormatStatus::class,
        ];
    }

    public function scopeForWeek(Builder $query, CarbonImmutable $weekStart): Builder
    {
        return $query->whereBetween('starts_at', [
            $weekStart->startOfDay(),
            $weekStart->addWeek()->subSecond(),
        ]);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('format_status', CalendarEventFormatStatus::NeedsReview);
    }

    public function durationInMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
