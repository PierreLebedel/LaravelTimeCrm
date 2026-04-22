<?php

use App\Support\DurationFormatter;

test('it formats minutes as hours and minutes', function () {
    expect(DurationFormatter::formatMinutes(0))->toBe('0h');
    expect(DurationFormatter::formatMinutes(60))->toBe('1h');
    expect(DurationFormatter::formatMinutes(285))->toBe('4h45');
    expect(DurationFormatter::formatMinutes(65))->toBe('1h05');
});
