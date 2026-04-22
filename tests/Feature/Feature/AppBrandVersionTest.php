<?php

test('it displays the nativephp app version in the navigation brand', function () {
    config()->set('nativephp.version', '9.9.9');

    $this->get('/')
        ->assertOk()
        ->assertSee('TimeCRM')
        ->assertSee('v9.9.9');
});
