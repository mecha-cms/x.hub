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
$omit = (array) (State::get('x.hub.omit', true) ?? []);

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = 'Method not allowed.';
    $r['status'] = 405;
    return $r;
}

$path = substr(rawurldecode($path), 7); // `strlen('/total/')`

if (!empty($deny)) {
    if ($test = $deny['/' . $path] ?? $deny[basename($path)] ?? 0) {
        $r['description'] = 'Forbidden.';
        $r['status'] = 403;
        return $r;
    }
}

$with_deep = array_key_exists('deep', $_GET);
$with_x = array_key_exists('x', $_GET);

$deep = $with_deep ? $_GET['deep'] : false;
$x = $with_x ? $_GET['x'] : null;

if (!(false === $deep || true === $deep || is_int($deep) && $deep >= 0)) {
    $r['description'] = 'Bad request.';
    $r['status'] = 400;
    return $r;
}

if (!(0 === $x || 1 === $x || null === $x || is_string($x))) {
    $r['description'] = 'Bad request.';
    $r['status'] = 400;
    return $r;
}

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = 'Bad request.';
    $r['status'] = 400;
    return $r;
}

if (!is_dir($path = PATH . D . $path)) {
    $r['description'] = 'Folder does not exist.';
    $r['status'] = 404;
    return $r;
}

if (!empty($omit)) {
    $total = 0;
    foreach (g($path, $x, $deep) as $k => $v) {
        $k = strtr(substr($k, strlen(PATH)), D, '/');
        if (!empty($omit[$k]) || !empty($omit[basename($k)])) {
            $total += 1;
        }
    }
} else {
    $total = q(g($path, $x, $deep));
}

$r['data']['total'] = $total;
$r['description'] = 'Okay.';
$r['status'] = 200;

return $r;