<?php

use App\Models\Client;
use App\Models\Project;

test('it trims the project name before saving', function () {
    $client = Client::factory()->create();

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => '  Plateforme  ',
    ]);

    expect($project->fresh()->name)->toBe('Plateforme');
});
