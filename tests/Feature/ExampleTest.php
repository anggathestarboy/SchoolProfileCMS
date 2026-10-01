<?php

test('the homepage returns not found when no entry is published there yet', function () {
    $response = $this->get(route('home'));

    $response->assertNotFound();
});
