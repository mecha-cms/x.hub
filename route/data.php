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

if (!(is_string($path) && "" !== $path)) {
    return ['status' => 400];
}

if (!(-1 === $sort[0] || 1 === $sort[1])) {
    return ['status' => 400];
}

if (!(is_float($sort[1]) || is_int($sort[1]) || is_string($sort[1]))) {
    return ['status' => 400,'x'=>$sort];
}

if (!($path = path(LOT . D . $path))) {
    return ['status' => 404];
}

$f = ($d = is_dir($path)) ? new Folder($path) : new File($path);

$lot = [
    '_seal' => $f->_seal,
    '_size' => $f->_size,
    '_time' => $f->_time,
    'name' => $f->name,
    'route' => $route = $f->route,
    'seal' => $f->seal,
    'size' => $f->size,
    'time' => (string) $f->time,
    'url' => $f->url
];

if (!$d) {
    $lot['type'] = $f->type;
    $lot['x'] = $f->x;
}

$r = [];

if ($d) {
    $values = g($f->path, $x, $deep, false);
    $lot['lot'] = [];
    $lot['total'] = $total = count($values);
    foreach ((new Anemone($values))->sort($sort)->chunk($chunk, $part - 1) as $v) {
        $ff = ($dd = is_dir($v)) ? new Folder($v) : new File($v);
        $rr = [];
        $rr['_seal'] = $ff->_seal;
        $rr['_size'] = $ff->_size;
        $rr['_time'] = $ff->_time;
        $rr['is']['file'] = !($rr['is']['folder'] = $dd);
        $rr['name'] = $ff->name;
        $rr['route'] = $ff->route;
        $rr['seal'] = $ff->seal;
        $rr['size'] = $ff->size;
        $rr['time'] = (string) $ff->time;
        $rr['url'] = $ff->url;
        if (!$dd) {
            $rr['type'] = $ff->type;
            $rr['x'] = $ff->x;
        }
        ksort($rr);
        ksort($rr['is']);
        $lot['lot'][] = $rr;
    }
    $r['has']['next'] = $part < ceil($total / $chunk);
    $r['has']['prev'] = $part > 1;
    $r['query']['chunk'] = $chunk;
    $r['query']['deep'] = $deep;
    $r['query']['part'] = $part;
    $r['query']['sort'] = $sort;
    $r['query']['x'] = $x;
}

!empty($lot) && ksort($lot);

$r['is']['file'] = !($r['is']['folder'] = $d);
$r['lot'] = $lot;
$r['status'] = 200;
$r['user'] = x\hub\user($status);

!empty($r) && ksort($r);
!empty($r['has']) && ksort($r['has']);
!empty($r['is']) && ksort($r['is']);

return $r;