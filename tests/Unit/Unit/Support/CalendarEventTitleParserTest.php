<?php

use App\Support\CalendarEventTitleParser;

test('it parses a title with a project', function () {
    expect(CalendarEventTitleParser::parse('ACME/Mobile App : offline sync'))
        ->toBe([
            'client_name' => 'ACME',
            'project_name' => 'Mobile App',
            'feature_description' => 'offline sync',
        ]);
});

test('it parses a title without a project', function () {
    expect(CalendarEventTitleParser::parse('ACME : weekly review'))
        ->toBe([
            'client_name' => 'ACME',
            'project_name' => null,
            'feature_description' => 'weekly review',
        ]);
});
