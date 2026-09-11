<?php

$path = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

if ($path === '/ok') {
    header('Content-Type: text/plain');
    header('X-Scanner-Test: transport-v1');

    echo 'hello';

    return;
}

if ($path === '/redirect') {
    header('Location: /ok', true, 302);

    echo 'redirect';

    return;
}

if ($path === '/large') {
    header('Content-Type: application/octet-stream');

    echo str_repeat('A', 4096);

    return;
}

http_response_code(404);
echo 'not-found';
