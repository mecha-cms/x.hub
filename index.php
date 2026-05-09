<?php

namespace x\hub {
    function r() {
        if (!($h = \status()[1]['authorization'] ?? "") || 0 !== \strncasecmp($h, 'bearer ', 7) || "" === ($token = \trim(\substr($h, 7)))) {
            return [
                'description' => \i('Missing or invalid %s value.', 'JSON Web Token'),
                'status' => 401
            ];
        }
        \extract(\lot(), \EXTR_SKIP);
        if (($r = v($token, (string) ($state->x->hub->pepper ?? "")))['status'] >= 400) {
            return $r;
        }
        if (0 === \strpos($key = $r['?']['sub'], '@')) {
            // Deleting the associated JWT file based on its `jti` field value will automatically reject the JWT token,
            // even if it has not yet expired. This can be used to create a “log out” feature for JWT, which would not
            // be possible with its native state-less design alone.
            if (null === ($tok = \content(\LOT . \D . 'user' . \D . \substr($key, 1) . \D . '+' . \D . '.hub' . \D . $r['?']['aud'] . \D . $r['?']['jti']))) {
                $r['description'] = \i('Stale token.');
                $r['status'] = 401;
                return $r;
            }
            $r['?']['tok'] = $tok;
        }
        return $r;
    }
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
        \Hook::let('content');
        return \Hook::fire('route.hub', [$content, '/' . \substr($path, \strlen($route) + 1), $query, $hash]);
    }
    function route__hub($content, $path, $query, $hash) {
        \type('application/json');
        $folder = __DIR__ . \D . 'engine' . \D . 'r';
        foreach (\step(\rawurldecode($path), '/') as $k => $v) {
            if (\is_file($file = $folder . \D . 'route' . \strtr($v, '/', \D) . '.php')) {
                $r = (function ($f, $path, $query, $hash) {
                    \extract(\lot(), \EXTR_SKIP);
                    try {
                        return require $f;
                    } catch (\Throwable $e) {
                        return ['e' => $e->getMessage(), 'status' => 400];
                    }
                })($file, $path, $query, $hash);
                if (\is_string($type = $_GET['type'] ?? 0)) {
                    foreach (\step($type, '/') as $k => $v) {
                        if (\is_file($file = $folder . \D . 'type' . \D . \strtr($v, '/', \D) . '.php')) {
                            (function ($f, $path, $query, $hash) use (&$r) {
                                \extract(\lot(), \EXTR_SKIP);
                                try {
                                    require $f;
                                } catch (\Throwable $e) {
                                    $r['e'] = $e->getMessage();
                                    $r['status'] = 400;
                                }
                            })($file, $path, $query, $hash);
                        }
                    }
                }
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
                return \json_encode($r, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
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
        return \json_encode([
            'description' => \i('Route does not exist.'),
            'status' => $status
        ]);
    }
    function user($r) {
        if (\is_int($r)) {
            return ['status' => -1];
        }
        if (0 === \strpos($key = $r['sub'], '@') && ($file = \exist(\LOT . \D . 'user' . \D . \substr($key, 1) . '.{' . \x\page\x() . '}', 1))) {
            $user = new \User($file);
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
        $r = ['?' => [], 'status' => 401];
        $t = \explode('.', $token);
        if (3 !== \count($t)) {
            $r['description'] = \i('Invalid JSON Web Token format.');
            return $r;
        }
        if (!\hash_equals($t[2], b64\x(\hash_hmac('sha256', $t[0] . '.' . $t[1], $pepper, true)))) {
            $r['description'] = \i('Invalid JSON Web Token signature.');
            return $r;
        }
        $r['?'] = \json_decode(b64\v($t[1]), true);
        if (($r['?']['exp'] ?? 0) < \time()) {
            if (0 === \strpos($key = $r['?']['sub'], '@')) {
                \delete(\LOT . \D . 'user' . \D . \substr($key, 1) . \D . '+' . \D . '.hub' . \D . $r['?']['aud'] . \D . $r['?']['jti']);
            }
            $r['description'] = \i('Stale token.');
            return $r;
        }
        $r['description'] = \i('Okay.');
        $r['status'] = 200;
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

namespace x\hub\is {
    function blob($f) {
        return file($f) && !text($f);
    }
    function file($f) {
        return $f instanceof \File;
    }
    function folder($f) {
        return $f instanceof \Folder;
    }
    function name($name, $allow = '!#$()+-._@') {
        if (!\is_string($name) || "" === $name || '.' === $name || '..' === $name) {
            return false;
        }
        $max = \strlen($name);
        for ($i = 0; $i < $max; ++$i) {
            $c = $name[$i];
            if (!($c >= '0' && $c <= '9') && !($c >= 'A' && $c <= 'Z') && !($c >= 'a' && $c <= 'z') && false === \strpos($allow, $c)) {
                return false;
            }
        }
        return '.' !== \substr($name, -1);
    }
    function route($route) {
        if (!\is_string($route)) {
            return false;
        }
        $r = [];
        foreach (\explode('/', \trim($route, '/')) as $v) {
            if (!name($v)) {
                return false;
            }
        }
        return true;
    }
    function text($f) {
        if (!file($f)) {
            return false;
        }
        $text = 'image/svg+xml' === ($type = $f->type ?? "") || 0 === \strpos($type, 'text/');
        if (0 === \strpos($type, 'application/')) {
            $text = false !== \strpos(',atom+xml,javascript,json,ld+json,mathml+xml,php,rss+xml,soap+xml,vnd.google-earth.kml+xml,x-empty,x-httpd-php,x-httpd-php-source,x-javascript,x-php,xhtml+xml,xml,', ',' . \substr($type, 12) . ',');
        }
        if (!$text) {
            $f = \fopen($f->path, 'rb');
            $test = \fread($f, 1024);
            \fclose($f);
            return false === \strpos($test, "\0");
        }
        return true;
    }
    function x($x) {
        return name($x, '.') && '.' !== $x[0] && '.' !== \substr($x, -1);
    }
}