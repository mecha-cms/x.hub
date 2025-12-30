<?php

if (!x\s\allow()) {
    return ['status' => 401];
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

if ("" === ($path = substr($path, 6))) { // `strlen('/file/')`
    return ['status' => 400];
}

if (!is_file($file = LOT . D . $path)) {
    return ['status' => 404];
}

$file = new File($file);

return [
    'lot' => [
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
    'status' => 200
];