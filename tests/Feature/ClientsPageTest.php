<?php

use App\Models\Client;
use App\Models\Project;
use Livewire\Livewire;

test('it creates a homonymous project when a client is created from the clients page', function () {
    Livewire::test('pages::clients')
        ->set('name', 'Acme')
        ->set('color', '#2563eb')
        ->set('billing_mode', 'daily')
        ->set('daily_rate', '700')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::query()->where('name', 'Acme')->sole();
    $project = Project::query()->where('client_id', $client->id)->where('name', 'Acme')->sole();

    expect($project->is_active)->toBeTrue();
});
