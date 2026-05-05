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

$f = is_dir($path) ? new Folder($path) : new File($path);

if ('content' === $key && !x\hub\is\text($f)) {
    $r['description'] = i('Unsupported media type.');
    $r['status'] = 415;
    return $r;
}

if (($value = $f->{$key}) && in_array($key, ['link', 'time'], true)) {
    $value = (string) $value;
}

if (!empty($_GET['base64'])) {
    $value = base64_encode($value);
}

$r['data'][$key] = $value;
$r['description'] = i('Okay.');
$r['status'] = 200;

return $r;