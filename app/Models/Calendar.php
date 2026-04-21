<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'calendar_account_id',
    'external_id',
    'name',
    'color',
    'timezone',
    'is_selected',
])]
class Calendar extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarFactory> */
    use HasFactory;

    public function account(): BelongsTo
    {
        return $this->belongsTo(CalendarAccount::class, 'calendar_account_id');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    protected function casts(): array
    {
        return [
            'is_selected' => 'bool',
        ];
    }
}
