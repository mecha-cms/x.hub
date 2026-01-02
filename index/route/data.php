<?php

if (is_int($status = x\hub\status())) {
    return ['status' => $status];
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$chunk = $_GET['chunk'] ?? 5;
$deep = $_GET['deep'] ?? 0;
$part = $_GET['part'] ?? 1;
$path = substr($path, 6); // `strlen('/data/')`
$sort = $_GET['sort'] ?? [1, 'route'];
$x = $_GET['x'] ?? null;

if (!is_string($path) || "" === $path) {
    return ['status' => 400];
}

if (!$path = path(LOT . D . $path)) {
    return ['status' => 404];
}

$of = ($folder = is_dir($path)) ? new Folder($path) : new File($path);

$data = [
    '_seal' => $of->_seal,
    '_size' => $of->_size,
    'name' => $of->name,
    'route' => $of->route,
    'seal' => $of->seal,
    'size' => $of->size,
    'type' => $of->type,
    'x' => $of->x
];

if ($folder) {
    $data['lot'] = [];
    foreach (g($of->path, $x, $deep) as $k => $v) {
        $r = '/' . substr($k, strlen(LOT . D));
        $value = [
            'data' => Hook::fire('link', ['/hub/data' . $r]),
            'is' => [
                'file' => 1 === $v,
                'folder' => 0 === $v
            ]
        ];
        if (1 === $v) {
            $f = new File($k);
            $value['blob'] = Hook::fire('link', ['/hub/blob' . $r]);
        } else {
            $f = new Folder($k);
        }
        $value['_seal'] = $f->seal;
        $value['_size'] = $f->_size;
        $value['seal'] = $f->seal;
        $value['size'] = $f->size;
        $data['lot'][] = $value;
    }
} else {
    $data['blob'] = Hook::fire('link', ['/hub/blob' . $of->route]);
}

asort($data);

return [
    'data' => $data,
    'status' => 200,
    'user' => x\hub\user($status)
];