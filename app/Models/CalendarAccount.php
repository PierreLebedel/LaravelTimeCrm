<?php

namespace App\Models;

use Database\Factories\CalendarAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'base_url',
    'username',
    'password',
    'default_client_id',
    'is_active',
    'last_synced_at',
])]
#[Hidden(['password'])]
class CalendarAccount extends Model
{
    /** @use HasFactory<CalendarAccountFactory> */
    use HasFactory;

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function defaultClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'default_client_id');
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'default_client_id' => 'integer',
            'is_active' => 'bool',
            'last_synced_at' => 'datetime',
        ];
    }
}
