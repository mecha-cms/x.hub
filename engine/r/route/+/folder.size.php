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

$path = substr(rawurldecode($path), 15); // `strlen('/+/folder.size/')`

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!is_dir($path = path(LOT . D . $path))) {
    $r['description'] = i('Folder does not exist.');
    $r['status'] = 404;
    return $r;
}

$r['description'] = i('Okay.');
$r['status'] = 200;
$r['value'] = (new Folder($path))->size;

return $r;