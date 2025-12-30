<?php

namespace x\s {
    function allow() {
        if (!($h = \status()[1]['authorization'] ?? "") || 0 !== \strncasecmp($h, 'bearer ', 7) || "" === ($token = \trim(\substr($h, 7)))) {
            return 0;
        }
        \extract(\lot(), \EXTR_SKIP);
        $pepper = (string) ($state->x->s->pepper ?? "");
        if (false === v($token, $pepper)) {
            return 0;
        }
        return 1;
    }
    function b64v(string $v) {
        return \base64_decode(\strtr($v, '-_', '+/'));
    }
    function b64x(string $v) {
        return \rtrim(\strtr(\base64_encode($v), '+/', '-_'), '=');
    }
    function route($content, $path, $query, $hash) {
        if ($content || !$path) {
            return $content;
        }
        \extract(\lot(), \EXTR_SKIP);
        $path = \trim($path, '/');
        $route = \trim($state->x->s->route ?? 's', '/');
        if (0 !== \strpos($path, $route . '/')) {
            return $content;
        }
        return \Hook::fire('route.s', [$content, '/' . \substr($path, \strlen($route) + 1), $query, $hash]);
    }
    function route__s($content, $path, $query, $hash) {
        \type('application/json');
        foreach (\step($path, '/') as $k => $v) {
            if (\is_file($file = __DIR__ . \D . 'index' . \D . 'route' . \strtr($v, '/', \D) . '.php')) {
                $r = (function ($f, $path, $query, $hash) {
                    \extract(\lot(), \EXTR_SKIP);
                    try {
                        return require $f;
                    } catch (\Throwable $e) {
                        return ['e' => $e->getMessage(), 'status' => 400];
                    }
                })($file, $path, $query, $hash);
                if (\is_int($status = $r['status'] ?? 0) && $status >= 100 && $status <= 599) {
                    \status($status, \array_replace([
                        'access-control-allow-headers' => 'authorization, content-type',
                        'access-control-allow-methods' => 'DELETE, GET, OPTIONS, PATCH, POST, PUT',
                        'access-control-allow-origin' => '*',
                        'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
                        'expires' => '0',
                        'pragma' => 'no-cache'
                    ], (array) ($r['headers'] ?? [])));
                }
                unset($r['headers']);
                return \json_encode($r);
            }
        }
        \status($status = 404, [
            'access-control-allow-headers' => 'authorization, content-type',
            'access-control-allow-methods' => 'DELETE, GET, OPTIONS, PATCH, POST, PUT',
            'access-control-allow-origin' => '*',
            'cache-control' => 'max-age=0, must-revalidate, no-cache, no-store',
            'expires' => '0',
            'pragma' => 'no-cache'
        ]);
        return \json_encode(['status' => $status]);
    }
    function v(string $token, string $pepper) {
        $r = \explode('.', $token);
        if (3 !== \count($r)) {
            return false; // Invalid token format
        }
        if (!\hash_equals($r[2], b64x(\hash_hmac('sha256', $r[0] . '.' . $r[1], $pepper, true)))) {
            return false; // Invalid token signature
        }
        $r = \json_decode(b64v($r[1]), true);
        if (($r['exp'] ?? 0) < \time()) {
            return false; // Stale token
        }
        return $r;
    }
    function x(array $lot, string $pepper) {
        $r = b64x(\json_encode(['alg' => 'HS256', 'typ' => 'JWT'])) . '.' . b64x(\json_encode($lot));
        return $r . '.' . b64x(\hash_hmac('sha256', $r, $pepper, true));
    }
    \Hook::set('route', __NAMESPACE__ . "\\route", 0);
    \Hook::set('route.s', __NAMESPACE__ . "\\route__s", 100);
}