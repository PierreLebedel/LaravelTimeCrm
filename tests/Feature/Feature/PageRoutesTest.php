<?php

test('it loads the main livewire pages', function () {
    foreach (['/', '/clients', '/projects', '/agendas', '/revue', '/analyse', '/queue'] as $uri) {
        $this->get($uri)->assertOk();
    }
});
