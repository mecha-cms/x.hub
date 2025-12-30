<?php

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$key = $_POST['key'] ?? "";
$pass = $_POST['pass'] ?? "";

// Currently, client(S) are forced to add a `@` prefix to the key input. In the future, key input that does not follow
// this convention may get special processing.
if (0 === strpos($key, '@')) {
    $key = substr($key, 1);
    $valid = false;
    if ("" === $key || "" === $pass) {
        return ['status' => 400];
    }
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
        $valid = password_verify($pass . '@' . $key, substr($h, 1));
    } else {
        $valid = $pass === $h;
    }
    // TODO: Generate refresh token
    if ($valid) {
        $pepper = (string) ($state->x->s->pepper ?? "");
        $t = time();
        return [
            'lot' => $lot = [
                'exp'  => $t + 60, // 1 minute
                'iat'  => $t,
                'sub'  => '@' . $key
            ],
            'status' => 200,
            'token' => x\s\x($lot, $pepper)
        ];
    }
    return ['status' => 401];
}

return ['status' => 400];