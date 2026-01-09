<?php

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$now = time();
$validity = is_int($v = $state->x->hub->validity ?? 600) ? $v : (is_string($v) && (false !== ($v = strtotime($v, 0))) ? $v : 600); // 10 minute(s) by default

$key = $_POST['key'] ?? "";
$pass = $_POST['pass'] ?? "";
$peer = $_POST['peer'] ?? ip() ?? "";

if (!is_string($key) || !is_string($pass) || !is_string($peer) || "" === $key || "" === $pass || "" === $peer) {
    return ['status' => 400];
}

// In order to verify using the built-in user feature, client(s) are required to include the `@` prefix. However, the
// client application can automatically prepend the prefix to the submitted key if it is missing, thus making the prefix
// optional. In the future, user key(s) submitted without the `@` prefix will be treated differently.
if (0 === strpos($key, '@')) {
    if (!isset($state->x->user)) {
        return ['status' => 424]; // Missing user extension
    }
    $name = substr($key, 1);
    $try_now = (int) (content($try_file = ($folder = LOT . D . 'user' . D . $name) . D . '.try' . D . md5($peer)) ?? 0);
    if (is_file($try_file) && ($now - filemtime($try_file) >= $validity)) {
        $try_now = 0; // Reset
    }
    if ($try_now >= 5) {
        return ['status' => 429]; // Too many verification request(s)
    }
    content($try_file, (string) ($try_now + 1), 0600);
    if (!is_file($file = $folder . '.page')) {
        return ['status' => 401]; // User does not exist
    }
    if (!is_file($f = $folder . D . 'pass.data')) {
        return ['status' => 401]; // User’s pass does not exist
    }
    if (0 === strpos($p = file_get_contents($f), P)) {
        if (!password_verify($pass . '@' . $name, substr($p, 1))) {
            return ['status' => 401]; // Wrong user’s pass
        }
    } else {
        if ($pass !== $p) {
            return ['status' => 401]; // Wrong user’s pass
        }
    }
    delete($try_file);
    $pepper = (string) ($state->x->hub->pepper ?? "");
    $user = new User($file);
    $user = [
        'author' => $user->author,
        'name' => $user->name,
        'status' => $user->status,
        'token' => ($token = content($token_file = $folder . D . '.hub' . D . ($id = bin2hex(random_bytes(8)))) ?? bin2hex(random_bytes(16))),
        'x' => $user->x
    ];
    // A refresh token file must be stored on the server to support the “refresh token” feature. Its name is based on
    // the `jti` field value in the JSON Web Token (JWT) payload. The rule is simple: If a refresh token exists in the
    // current user data, but the associated JWT’s `jti` field value file does not exist, then the JWT token cannot be
    // refreshed using it.
    content($token_file, $token, 0600);
    return [
        'status' => 200,
        'token' => x\hub\x([
            'aud' => $peer,
            'exp' => $now + $validity,
            'iat' => $now,
            'jti' => $id,
            'sub' => $key
        ], $pepper),
        'user' => $user
    ];
}

return ['status' => 400];