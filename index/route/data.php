<?php

if (is_int($status = x\hub\status())) {
    return ['status' => $status];
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$chunk = $_GET['chunk'] ?? 5;
$deep = $_GET['deep'] ?? 0;
$of = $_GET['of'] ?? "";
$part = $_GET['part'] ?? 1;
$sort = $_GET['sort'] ?? [1, 'route'];
$x = $_GET['x'] ?? null;

if (!is_string($of) || "" === $of) {
    return ['status' => 400];
}

if (!$of = path(LOT . D . $of)) {
    return ['status' => 404];
}

$of = ($folder = is_dir($of)) ? new Folder($of) : new File($of);

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
    foreach (g($of, $x, $deep) as $v) {
        $data['lot'][] = ['data' => long('/hub/data?of=' . urlencode(substr($v, strlen(LOT . D))))];
    }
}

asort($data);

return [
    'data' => $data,
    'status' => 200,
    'user' => x\hub\user()
];