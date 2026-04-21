<?php

namespace App\Models;

use App\Enums\BillingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'billing_mode',
    'hourly_rate',
    'daily_rate',
    'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    protected function casts(): array
    {
        return [
            'billing_mode' => BillingMode::class,
            'hourly_rate' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }

    public function calculateCostInEuros(int $minutes): float
    {
        $hours = $minutes / 60;

        if ($this->billing_mode === BillingMode::Daily) {
            $dailyRate = (float) ($this->daily_rate ?? 0);

            return round(($hours / config('crm.hours_per_day')) * $dailyRate, 2);
        }

        $hourlyRate = (float) ($this->hourly_rate ?? 0);

        return round($hours * $hourlyRate, 2);
    }
}
