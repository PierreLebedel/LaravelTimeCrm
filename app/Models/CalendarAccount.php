<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'base_url',
    'username',
    'password',
    'is_active',
    'last_synced_at',
])]
#[Hidden(['password'])]
class CalendarAccount extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarAccountFactory> */
    use HasFactory;

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'bool',
            'last_synced_at' => 'datetime',
        ];
    }
}
