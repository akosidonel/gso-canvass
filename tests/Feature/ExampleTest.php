<?php

test('the home page requires sign in', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
