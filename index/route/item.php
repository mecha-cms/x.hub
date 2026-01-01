<?php

if (is_int($r = x\hub\pact())) {
    return ['status' => $r];
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

if ("" === ($path = substr($path, 6))) { // `strlen('/item/')`
    return ['status' => 400];
}

if (!is_file($file = LOT . D . $path)) {
    return ['status' => 404];
}

$file = new File($file);

return [
    'data' => [
        '_seal' => $file->_seal,
        '_size' => $file->_size,
        'name' => $file->name,
        'route' => $file->route,
        'seal' => $file->seal,
        'size' => $file->size,
        'type' => $file->type,
        'url' => $file->url,
        'x' => $file->x
    ],
    'pact' => $r,
    'status' => 200
];