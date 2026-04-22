<?php

namespace App\Support;

class DurationFormatter
{
    public static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dh%02d', $hours, $remainingMinutes);
    }
}
