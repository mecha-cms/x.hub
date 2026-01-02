<?php

namespace x\hub {
    function route($content, $path, $query, $hash) {
        if ($content || !$path) {
            return $content;
        }
        \extract(\lot(), \EXTR_SKIP);
        $path = \trim($path, '/');
        $route = \trim($state->x->hub->route ?? 'hub', '/');
        if (0 !== \strpos($path, $route . '/')) {
            return $content;
        }
        return \Hook::fire('route.hub', [$content, '/' . \substr($path, \strlen($route) + 1), $query, $hash]);
    }
    function route__hub($content, $path, $query, $hash) {
        \type('application/json');
        foreach (\step($path, '/') as $k => $v) {
            if (\is_file($file = __DIR__ . \D . 'r' . \strtr($v, '/', \D) . '.php')) {
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
    function status() {
        if (!($h = \status()[1]['authorization'] ?? "") || 0 !== \strncasecmp($h, 'bearer ', 7) || "" === ($token = \trim(\substr($h, 7)))) {
            return 401;
        }
        \extract(\lot(), \EXTR_SKIP);
        if (\is_int($r = v($token, (string) ($state->x->hub->pepper ?? "")))) {
            return $r;
        }
        if (0 === \strpos($key = $r['sub'], '@')) {
            if (null === ($tok = \content(\LOT . \D . 'user' . \D . \substr($key, 1) . \D . '.hub' . \D . $r['jti']))) {
                return 401;
            }
            $r['tok'] = $tok;
        }
        return $r;
    }
    function user($r) {
        if (\is_int($r)) {
            return ['status' => -1];
        }
        if (0 === \strpos($key = $r['sub'], '@')) {
            $user = new \User(($folder = \LOT . \D . 'user' . \D . \substr($key, 1)) . '.page');
            return [
                'author' => $user->author,
                'name' => $user->name,
                'status' => $user->status,
                'token' => $r['tok'] ?? null,
                'x' => $user->x
            ];
        }
        return ['status' => -1];
    }
    function v(string $token, string $pepper) {
        $r = \explode('.', $token);
        if (3 !== \count($r)) {
            return 401; // Invalid token format
        }
        if (!\hash_equals($r[2], b64\x(\hash_hmac('sha256', $r[0] . '.' . $r[1], $pepper, true)))) {
            return 401; // Invalid token signature
        }
        $r = \json_decode(b64\v($r[1]), true);
        if (($r['exp'] ?? 0) < \time()) {
            return 401; // Stale token
        }
        return $r;
    }
    function x(array $data, string $pepper) {
        $r = b64\x(\json_encode(['alg' => 'HS256', 'typ' => 'JWT'])) . '.' . b64\x(\json_encode($data));
        return $r . '.' . b64\x(\hash_hmac('sha256', $r, $pepper, true));
    }
    \Hook::set('route', __NAMESPACE__ . "\\route", 0);
    \Hook::set('route.hub', __NAMESPACE__ . "\\route__hub", 100);
}

namespace x\hub\b64 {
    function v(string $v) {
        return \base64_decode(\strtr($v, '-_', '+/'));
    }
    function x(string $v) {
        return \rtrim(\strtr(\base64_encode($v), '+/', '-_'), '=');
    }
}