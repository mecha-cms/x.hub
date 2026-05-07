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

if ('content' === $key) {
    if (!x\hub\is\text($f)) {
        $r['description'] = i('Unsupported media type.');
        $r['status'] = 415;
        return $r;
    }
    $r['data']['content'] = !empty($_GET['base64']) ? base64_encode($f->content) : $f->content;
    $r['data']['type'] = $f->type;
} else if ('link' === $key) {
    $r['data']['link'] = (string) $f->link;
} else if (in_array($key, ['seal', 'size', 'time'], true)) {
    $r['data']['_' . $key] = $f->{'_' . $key};
    $r['data'][$key] = (string) $f->{$key};
} else {
    $r['data'][$key] = $f->{$key};
}

$r['description'] = i('Okay.');
$r['status'] = 200;

return $r;