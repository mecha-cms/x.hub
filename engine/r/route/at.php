<?php

if (($r = x\hub\r())['status'] >= 400) {
    if (defined('TEST') && TEST) {} else {
        unset($r['?']);
    }
    return $r;
}

$r['user'] = x\hub\user($r['?']);

if (defined('TEST') && TEST) {} else {
    unset($r['?']);
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = i('Method not allowed.');
    $r['status'] = 405;
    return $r;
}

$path = substr(rawurldecode($path), 4); // `strlen('/at/')`

$chunk = $_GET['chunk'] ?? 5;
$deep = $_GET['deep'] ?? 0;
$limit = $_GET['limit'] ?? $chunk;
$part = $_GET['part'] ?? 1;
$sort = array_replace([1, 'route'], (array) ($_GET['sort'] ?? 1));
$x = $_GET['x'] ?? null;

if (!(is_int($chunk) && $chunk > 0)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(false === $deep || true === $deep || is_int($deep) && $deep >= 0)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(is_int($limit) && $limit > 0)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(is_int($part) && $part > 0)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (($with_limit = array_key_exists('limit', $_GET)) && (($with_chunk = array_key_exists('chunk', $_GET)) || ($with_part = array_key_exists('part', $_GET)))) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(-1 === $sort[0] || 1 === $sort[0])) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!(is_float($sort[1]) || is_int($sort[1]) || is_string($sort[1]))) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

// Avoid sort key(s) such as `__construct`, `__sleep`, etc.
if (is_string($sort[1]) && 0 === strpos($sort[1], '__')) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!($path = path(PATH . D . $path))) {
    $r['description'] = i('File or folder does not exist.');
    $r['status'] = 404;
    return $r;
}

$f = is_dir($path) ? new Folder($path) : new File($path);

$data = [
    '_seal' => $f->_seal,
    '_size' => $f->_size,
    '_time' => $f->_time,
    'id' => $f->id,
    'link' => (string) $f->link,
    'name' => $f->name,
    'route' => $route = $f->route,
    'seal' => $f->seal,
    'size' => $f->size,
    'time' => (string) $f->time
];

if ($r['has']['parent'] = false !== strpos(substr($path, strlen(PATH . D)), D)) {
    $ff = $f->parent;
    $data['$']=[];
    $data['parent'] = [
        '_seal' => $ff->_seal,
        '_size' => null, // Use `/hub/+/_size/…`
        '_time' => $ff->_time,
        'id' => $ff->id,
        'is' => [
            'blob' => false,
            'file' => false,
            'folder' => true,
            'text' => false
        ],
        'link' => (string) $ff->link,
        'name' => $ff->name,
        'route' => $ff->route,
        'seal' => $ff->seal,
        'size' => null, // Use `/hub/+/size/…`
        'time' => (string) $ff->time
    ];
}

if (x\hub\is\folder($f)) {
    $values = g($f->path, $x, $deep, false);
    $data['children'] = [];
    $data['total'] = $total = count($values);
    $values = (new Batch($values))->sort(function ($a, $b) use (&$sort) {
        $a = ($a_is_folder = is_dir($a)) ? new Folder($a) : new File($a);
        $b = ($b_is_folder = is_dir($b)) ? new Folder($b) : new File($b);
        // Folder first
        if ($a_is_folder !== $b_is_folder) {
            return $a_is_folder ? -1 : 1;
        }
        if (!isset($a->{$sort[1]}) || !isset($b->{$sort[1]})) {
            return 0;
        }
        return 1 === $sort[0] ? $a->{$sort[1]} <=> $b->{$sort[1]} : $b->{$sort[1]} <=> $a->{$sort[1]};
    });
    if ($with_limit) {
        $values = $values->limit($limit);
    } else {
        $values = $values->chunk($chunk, $part - 1);
    }
    foreach ($values as $v) {
        $ff = is_dir($v) ? new Folder($v) : new File($v);
        $rr = [];
        $rr['_seal'] = $ff->_seal;
        $rr['_time'] = $ff->_time;
        $rr['id'] = $ff->id;
        $rr['is']['blob'] = x\hub\is\blob($ff);
        $rr['is']['file'] = x\hub\is\file($ff);
        $rr['is']['folder'] = x\hub\is\folder($ff);
        $rr['is']['text'] = x\hub\is\text($ff);
        $rr['link'] = (string) $ff->link;
        $rr['name'] = $ff->name;
        $rr['route'] = $ff->route;
        $rr['seal'] = $ff->seal;
        $rr['time'] = (string) $ff->time;
        if (x\hub\is\file($ff)) {
            $rr['_size'] = $ff->_size;
            $rr['size'] = $ff->size;
            $rr['type'] = $ff->type;
            $rr['x'] = $ff->x;
        } else {
            $rr['_size'] = $rr['size'] = null; // Use `/hub/+/size/…`
        }
        ksort($rr);
        ksort($rr['is']);
        $data['children'][] = $rr;
    }
    $r['has']['children'] = $total > 0;
    $r['has']['next'] = $with_limit ? false : $part < ceil($total / $chunk);
    $r['has']['prev'] = $with_limit ? false : $part > 1;
    if ($with_limit) {
        $r['query']['limit'] = $limit;
    } else {
        $r['query']['chunk'] = $chunk;
        $r['query']['part'] = $part;
    }
    $r['query']['deep'] = $deep;
    $r['query']['sort'] = $sort;
    $r['query']['x'] = $x;
} else {
    $data['type'] = $f->type;
    $data['x'] = $f->x;
    if (array_intersect_key($_GET, [
        'chunk' => 1,
        'deep' => 1,
        'limit' => 1,
        'part' => 1,
        'sort' => 1,
        'x' => 1
    ])) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
}

!empty($data) && ksort($data);

$r['data'] = $data;
$r['description'] = i('Okay.');
$r['is']['blob'] = x\hub\is\blob($f);
$r['is']['file'] = x\hub\is\file($f);
$r['is']['folder'] = x\hub\is\folder($f);
$r['is']['text'] = x\hub\is\text($f);
$r['status'] = 200;

!empty($r) && ksort($r);
!empty($r['has']) && ksort($r['has']);
!empty($r['is']) && ksort($r['is']);
!empty($r['query']) && ksort($r['query']);

return $r;