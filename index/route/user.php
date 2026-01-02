<?php

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$key = $_POST['key'] ?? "";
$pass = $_POST['pass'] ?? "";

if (!is_string($key) || !is_string($pass) || "" === $key || "" === $pass) {
    return ['status' => 400];
}

// Currently, client(S) are forced to add a `@` prefix to the key input.
// In the future, key input that does not follow this convention may get special processing.
if (0 === strpos($key, '@')) {
    $key = substr($key, 1);
    if (!isset($state->x->user)) {
        return ['status' => 424];
    }
    if (!is_file($file = LOT . D . 'user' . D . $key . '.page')) {
        return ['status' => 401];
    }
    if (!is_file($f = substr($file, 0, -5) . D . 'pass.data')) {
        return ['status' => 401];
    }
    if (0 === strpos($h = file_get_contents($f), P)) {
        if (!password_verify($pass . '@' . $key, substr($h, 1))) {
            return ['status' => 401];
        }
    } else {
        if ($pass !== $h) {
            return ['status' => 401];
        }
    }
    $now = time();
    $pepper = (string) ($state->x->hub->pepper ?? "");
    $user = new User($file);
    $user = [
        'author' => $user->author,
        'name' => $user->name,
        'status' => $user->status,
        'token' => bin2hex(random_bytes(16)),
        'x' => $user->x
    ];
    return [
        'status' => 200,
        'token' => x\hub\x([
            'aud' => "",
            'exp' => $now + 600, // 10 minute(s) from now
            'iat' => $now,
            'jti' => bin2hex(random_bytes(16)),
            'sub' => '@' . $key
        ], $pepper),
        'user' => $user
    ];
}

return ['status' => 400];