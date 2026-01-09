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
$sort = array_replace([1, 'route'], (array) ($_GET['sort'] ?? 1));
$x = $_GET['x'] ?? null;

if (!(is_int($chunk) && $chunk > 0)) {
    return ['status' => 400];
}

if (!(false === $deep || true === $deep || is_int($deep) && $deep >= 0)) {
    return ['status' => 400];
}

if (!(is_int($part) && $part > 0)) {
    return ['status' => 400];
}

if (!is_string($path) || "" === $path) {
    return ['status' => 400];
}

if (-1 !== $sort[0] && 1 !== $sort[0]) {
    return ['status' => 400];
}

if (!is_float($sort[1]) && !is_int($sort[1]) && !is_string($sort[1])) {
    return ['status' => 400,'x'=>$sort];
}

if (!$path = path(LOT . D . $path)) {
    return ['status' => 404];
}

$of = ($d = is_dir($path)) ? new Folder($path) : new File($path);

$data = [
    '_seal' => $of->_seal,
    '_size' => $of->_size,
    'name' => $of->name,
    'route' => $route = $of->route,
    'seal' => $of->seal,
    'size' => $of->size
];

if (!$d) {
    $data['type'] = $of->type;
    $data['x'] = $of->x;
}

$links = [];

if ($d) {
    $values = g($of->path, $x, $deep, false);
    $data['lot'] = [];
    $data['total'] = $total = count($values);
    foreach ((new Anemone($values))->sort($sort)->chunk($chunk, $part - 1) as $v) {
        $r = '/' . substr($v, strlen(LOT . D));
        $value = [
            'is' => [
                'file' => !($d = is_dir($v)),
                'folder' => $d
            ],
            'links' => ['data' => Hook::fire('link', ['/hub/data' . $r])]
        ];
        if ($d) {
            $f = new Folder($v);
        } else {
            $f = new File($v);
            $value['links']['blob'] = Hook::fire('link', ['/hub/blob' . $r]);
        }
        ksort($value['links']);
        $value['_seal'] = $f->seal;
        $value['_size'] = $f->_size;
        $value['name'] = $f->name;
        $value['route'] = $f->route;
        $value['seal'] = $f->seal;
        $value['size'] = $f->size;
        if (!$d) {
            $value['type'] = $f->type;
            $value['x'] = $f->x;
        }
        $data['lot'][] = $value;
    }
    $data['state']['chunk'] = $chunk;
    $data['state']['deep'] = $deep;
    $data['state']['part'] = $part;
    $data['state']['sort'] = $sort;
    $data['state']['x'] = $x;
    $links['current'] = Hook::fire('link', ['/hub/data' . $route . To::query($q = $data['state'])]);
    if ($part < ceil($total / $chunk)) {
        $links['next'] = Hook::fire('link', ['/hub/data' . $route . To::query(array_replace($q, ['part' => $part + 1]))]);
    }
    if ($part > 1) {
        $links['prev'] = Hook::fire('link', ['/hub/data' . $route . To::query(array_replace($q, ['part' => $part - 1]))]);
    }
} else {
    $links['blob'] = Hook::fire('link', ['/hub/blob' . $route]);
    $links['current'] = Hook::fire('link', ['/hub/data' . $route]);
}

ksort($data);

return [
    'data' => $data,
    'links' => $links ? $links : ((object) []),
    'status' => 200,
    'user' => x\hub\user($status)
];