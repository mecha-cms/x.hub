<?php

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$key = substr(trim($path, '/'), strlen(basename(__FILE__, '.php')) + 1);

return [
    'lot' => array_replace("" !== $key ? ['key' => $key] : [], ['value' => token($key)]),
    'status' => 200
];