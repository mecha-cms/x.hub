<?php

if (is_int($status = x\hub\status())) {
    return null;
}

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    return null;
}

$path = substr($path, 6); // `strlen('/blob/')`
$raw = !empty($_POST['raw']);

if (!is_string($path) || "" === $path) {
    return null;
}

if (!is_file($path = LOT . D . $path)) {
    return null;
}

$file = new File($path);

status(200);
type($raw ? 'text/plain' : $file->type);

echo $file->content;