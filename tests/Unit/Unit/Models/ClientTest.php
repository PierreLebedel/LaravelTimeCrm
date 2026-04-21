<?php

use App\Enums\BillingMode;
use App\Models\Client;

test('it calculates hourly costs from event minutes', function () {
    $client = Client::factory()->create([
        'billing_mode' => BillingMode::Hourly,
        'hourly_rate' => 120,
    ]);

    expect($client->calculateCostInEuros(90))->toBe(180.0);
});

test('it calculates daily costs from event minutes', function () {
    $client = Client::factory()->daily()->create([
        'daily_rate' => 700,
    ]);

    expect($client->calculateCostInEuros(210))->toBe(350.0);
});
