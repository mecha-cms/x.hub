<?php

if (is_int($status = x\hub\status())) {
    return ['status' => $status];
}

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$path = substr($path, 6); // `strlen('/blob/')`
$raw = !empty($_POST['raw']);

if (!(is_string($path) && "" !== $path)) {
    return ['status' => 400];
}

if (!is_file($path = LOT . D . $path)) {
    return ['status' => 404];
}

status(200);

type($raw ? 'text/plain' : (false !== ($type = mime_content_type($path)) ? $type : 'application/octet-stream'));

echo content($path);

exit;