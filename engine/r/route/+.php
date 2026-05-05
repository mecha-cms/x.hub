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

$path = substr(rawurldecode($path), 3); // `strlen('/+/')`

[$key, $path] = explode('/', $path, 2);
if (!(is_string($path) && "" !== $path)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!($path = path(PATH . D . $path))) {
    $r['description'] = i('File or folder does not exist.');
    $r['status'] = 404;
    return $r;
}

// TODO: Support more key(s)
if (!in_array($key, [
    '_seal',
    '_size',
    '_time',
    'content',
    'id',
    'link',
    'name',
    'route',
    'seal',
    'size',
    'time',
    'x'
], true)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

$r['data'][$key] = (is_dir($path) ? new Folder($path) : new File($path))->{$key};
$r['description'] = i('Okay.');
$r['status'] = 200;

return $r;