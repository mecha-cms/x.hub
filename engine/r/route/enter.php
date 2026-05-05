<?php

$r = [];

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = i('Method not allowed.');
    $r['status'] = 405;
    return $r;
}

$now = time();
$validity = is_int($v = $state->x->hub->validity ?? 600) ? $v : (is_string($v) && (false !== ($v = strtotime($v, 0))) ? $v : 600); // 10 minute(s) by default

$key = $_POST['key'] ?? "";
$pass = $_POST['pass'] ?? "";
$peer = $_POST['peer'] ?? ip() ?? "";

if (!is_string($key) || !is_string($pass) || !is_string($peer) || "" === $key || "" === $pass || "" === $peer) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if ('.' === $peer || '..' === $peer || strspn($peer, '-.0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz') !== strlen($peer)) {
    $r['description'] = i('Invalid peer value.');
    $r['status'] = 400;
    return $r;
}

// In order to verify using the built-in user feature, client(s) are required to include the `@` prefix. However, the
// client application can automatically prepend the prefix to the submitted key if it is missing, thus making the prefix
// optional. In the future, user key(s) submitted without the `@` prefix will be treated differently.
if (0 === strpos($key, '@')) {
    if (!isset($state->x->user)) {
        $r['description'] = i('Missing `user` extension.');
        $r['status'] = 424;
        return $r;
    }
    $name = substr($key, 1);
    $try_now = (int) (content($try_file = ($folder = LOT . D . 'user' . D . $name) . D . '+' . D . '.try' . D . $peer) ?? 0);
    if (is_file($try_file) && ($now - filemtime($try_file) >= $validity)) {
        $try_now = 0; // Reset
    }
    if ($try_now >= 5) {
        $r['description'] = i('Too many verification requests.');
        $r['status'] = 429;
        return $r;
    }
    $file = exist($folder . '.{' . x\page\x() . '}', 1);
    if (!$file || !is_file($file)) {
        $r['description'] = i(defined('TEST') && TEST ? 'User does not exist.' : 'Invalid key or pass.');
        $r['status'] = 404;
        return $r;
    }
    content($try_file, (string) ($try_now + 1), 0600);
    $f = exist($folder . D . '+' . D . 'pass.{' . x\page\x() . '}', 1);
    if (!$f || !is_file($f)) {
        $r['description'] = i(defined('TEST') && TEST ? 'User\'s pass does not exist.' : 'Invalid key or pass.');
        $r['status'] = 403;
        return $r;
    }
    if (0 === strpos($p = file_get_contents($f), P)) {
        if (!password_verify($pass . '@' . $name, substr($p, 1))) {
            $r['description'] = i(defined('TEST') && TEST ? 'Wrong user\'s pass.' : 'Invalid key or pass.');
            $r['status'] = 401;
            return $r;
        }
    } else {
        if ($pass !== $p) {
            $r['description'] = i(defined('TEST') && TEST ? 'Wrong user\'s pass.' : 'Invalid key or pass.');
            $r['status'] = 401;
            return $r;
        }
    }
    delete($try_file);
    $pepper = (string) ($state->x->hub->pepper ?? "");
    $user = new User($file);
    $user = [
        'author' => $user->author,
        'name' => $user->name,
        'status' => $user->status,
        'token' => ($token_value = content($token_file = $folder . D . '+' . D . '.hub' . D . $peer . D . ($id = bin2hex(random_bytes(8)))) ?? bin2hex(random_bytes(16))),
        'x' => $user->x
    ];
    // A refresh token file must be stored on the server to support the “refresh token” feature. Its name is based on
    // the `jti` field value in the JSON Web Token (JWT) payload. The rule is simple: If a refresh token exists in the
    // current user data, but the associated JWT’s `jti` field value file does not exist, then the JWT token cannot be
    // refreshed using it.
    content($token_file, $token_value, 0600);
    $r['data']['hub'] = x\hub\x([
        'aud' => $peer,
        'exp' => $now + $validity,
        'iat' => $now,
        'jti' => $id,
        'sub' => $key
    ], $pepper);
    $r['description'] = i('Okay.');
    $r['status'] = 200;
    $r['user'] = $user;
    return $r;
}

$r['description'] = i('Bad request.');
$r['status'] = 400;

return $r;