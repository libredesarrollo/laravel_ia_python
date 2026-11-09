<?php

test('the application redirects guests to the corporate chat', function () {
    $this->get('/')->assertRedirect(route('admin.chat.index'));
});
