<?php

namespace x\s {
    function route($content, $path, $query, $hash) {
        if ($content || !$path) {
            return $content;
        }
        \extract(\lot(), \EXTR_SKIP);
        $route = \trim($state->x->s->route ?? 's', '/');
        if (0 !== \strpos($path, '/' . $route . '/')) {
            return $content;
        }
        return \Hook::fire('route.s', [$content, \substr($path, \strlen('/' . $route)), $query, $hash]);
    }
    function route__s($content, $path, $query, $hash) {
        \type('application/json');
        \extract(\lot(), \EXTR_SKIP);
        $pepper = (string) ($state->x->s->pepper ?? "");
        if ('POST' === $_SERVER['REQUEST_METHOD'] && '/enter' === $path) {
            $key = $_POST['key'] ?? "";
            $pass = $_POST['pass'] ?? "";
            if ("" === $key || "" === $pass) {
                \status(401);
                return '{}';
            }
            $t = \time();
            return \json_encode(['token' => x([
                'exp'  => $t + 60, // 1 minute
                'iat'  => $t,
                'sub'  => '@taufik-nurrohman'
            ], $pepper)]);
        }
        if (\is_string($r = \status()[1]['authorization'] ?? 0)) {
            $token = $r;
        } else if (\is_string($r = $_GET['token'] ?? 0)) {
            $token = $r;
        } else {
            $token = "";
        }
        \status(false === ($v = v($token, $pepper)) ? 401 : 200);
        return \json_encode($v);
    }
    function x(array $lot, string $pepper) {
        $r = b64\x(\json_encode(['alg' => 'HS256', 'typ' => 'JWT'])) . '.' . b64\x(\json_encode($lot));
        return $r . '.' . b64\x(\hash_hmac('sha256', $r, $pepper, true));
    }
    function v(string $token, string $pepper) {
        $r = \explode('.', $token);
        if (3 !== \count($r)) {
            return false; // Invalid token format
        }
        if (!\hash_equals($r[2], b64\x(\hash_hmac('sha256', $r[0] . '.' . $r[1], $pepper, true)))) {
            return false; // Invalid token signature
        }
        $r = \json_decode(b64\v($r[1]), true);
        if (($r['exp'] ?? 0) < \time()) {
            return false; // Stale token
        }
        return $r;
    }
    \Hook::set('route', __NAMESPACE__ . "\\route", 0);
    \Hook::set('route.s', __NAMESPACE__ . "\\route__s", 100);
}

namespace x\s\b64 {
    function x(string $v) {
        return \rtrim(\strtr(\base64_encode($v), '+/', '-_'), '=');
    }
    function v(string $v) {
        return \base64_decode(\strtr($v, '-_', '+/'));
    }
}