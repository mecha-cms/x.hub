<?php

if (($r = x\hub\r())['status'] >= 400) {
    if (!defined('TEST') || !TEST) {
        unset($r['?']);
    }
    return $r;
}

$r['query'] = [];
$r['user'] = x\hub\user($r['?']);

if (!defined('TEST') || !TEST) {
    unset($r['?']);
}

$deny = (array) (State::get('x.hub.deny', true) ?? []);

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = 'Method not allowed.';
    $r['status'] = 405;
    return $r;
}

$p = strtolower($_SERVER['REQUEST_METHOD'] ?? "");
$path = substr(rawurldecode($path), 9); // `strlen('/content/')`

if (!empty($deny)) {
    if ($test = $deny['/' . $path] ?? $deny[basename($path)] ?? 0) {
        if (is_array($test)) {
            if (!empty($test[$p])) {
                $r['description'] = 'Bad request.';
                $r['status'] = 400;
                return $r;
            }
        } else {
            $r['description'] = 'Forbidden.';
            $r['status'] = 403;
            return $r;
        }
    }
}

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = 'Bad request.';
    $r['status'] = 400;
    return $r;
}

if (!is_file($path = PATH . D . $path)) {
    $r['description'] = 'File does not exist.';
    $r['status'] = 404;
    return $r;
}

if (!x\hub\is\text($f = new File($path))) {
    $r['description'] = 'Unsupported media type.';
    $r['status'] = 415;
    return $r;
}

$r['data']['content'] = $f->content();
$r['data']['type'] = $f->type();
$r['description'] = 'Okay.';
$r['status'] = 200;

return $r;