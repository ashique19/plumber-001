<?php

it('returns styled 404 for unknown routes', function () {
    $this->withoutVite();

    $this->get('/definitely-not-a-route-'.uniqid())
        ->assertNotFound()
        ->assertSee(__('errors.not_found_heading'), false)
        ->assertSee('404', false);
});

it('exposes health check json', function () {
    $this->get('/healthz')
        ->assertOk()
        ->assertJsonStructure(['status', 'checks' => ['app', 'database'], 'time']);
});
