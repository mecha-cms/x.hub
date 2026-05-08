<?php

if (($r = x\hub\r())['status'] >= 400) {
    if (defined('TEST') && TEST) {} else {
        unset($r['?']);
    }
    return $r;
}

$r['query'] = [];
$r['user'] = x\hub\user($r['?']);

if (defined('TEST') && TEST) {} else {
    unset($r['?']);
}

$path = substr(rawurldecode($path), 4); // `strlen('/at/')`

$q = $_SERVER['REQUEST_METHOD'];

if ('DELETE' === $q) {}

if ('GET' === $q) {
    $with_at = array_key_exists('at', $_GET);
    $with_chunk = array_key_exists('chunk', $_GET);
    $with_deep = array_key_exists('deep', $_GET);
    $with_limit = array_key_exists('limit', $_GET);
    $with_part = array_key_exists('part', $_GET);
    $with_sort = array_key_exists('sort', $_GET);
    $with_x = array_key_exists('x', $_GET);
    $at = $with_at ? $_GET['at'] : 0;
    $chunk = $with_chunk ? $_GET['chunk'] : 5;
    $deep = $with_deep ? $_GET['deep'] : 0;
    $limit = $with_limit ? $_GET['limit'] : $chunk;
    $part = $with_part ? $_GET['part'] : 1;
    $sort = array_replace([1, 'route'], (array) ($_GET['sort'] ?? 1));
    $x = $_GET['x'] ?? null;
    if ($with_limit && ($with_chunk || $with_part)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(is_int($at) && $at >= 0)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!$with_part && $with_at) {
        $part = $at + 1;
    }
    if (!(is_int($chunk) && $chunk > 0)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(false === $deep || true === $deep || is_int($deep) && $deep >= 0)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(is_int($limit) && $limit > 0)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(is_int($part) && $part > 0)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(is_string($path) && "" !== $path)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(-1 === $sort[0] || 1 === $sort[0])) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!(is_float($sort[1]) || is_int($sort[1]) || is_string($sort[1]))) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    // Avoid sort key(s) such as `__construct`, `__sleep`, etc.
    if (is_string($sort[1]) && 0 === strpos($sort[1], '__')) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!($path = path(PATH . D . $path))) {
        $r['description'] = i('File or folder does not exist.');
        $r['status'] = 404;
        return $r;
    }
    $f = is_dir($path) ? new Folder($path) : new File($path);
    $data = [
        '_seal' => $f->_seal,
        '_size' => $f->_size,
        '_time' => $f->_time,
        'id' => $f->id,
        'link' => (string) $f->link,
        'name' => $f->name,
        'route' => $route = $f->route,
        'seal' => $f->seal,
        'size' => $f->size,
        'time' => (string) $f->time
    ];
    if ($data['has']['parent'] = false !== strpos(substr($path, strlen(PATH . D)), D)) {
        $ff = $f->parent;
        $data['parent'] = [
            '_seal' => $ff->_seal,
            '_size' => null, // Use `/hub/+/size/…`
            '_time' => $ff->_time,
            'id' => $ff->id,
            'is' => [
                'blob' => false,
                'file' => false,
                'folder' => true,
                'text' => false
            ],
            'link' => (string) $ff->link,
            'name' => $ff->name,
            'route' => $ff->route,
            'seal' => $ff->seal,
            'size' => null, // Use `/hub/+/size/…`
            'time' => (string) $ff->time
        ];
    }
    if (x\hub\is\folder($f)) {
        $values = g($f->path, $x, $deep, false);
        $data['children'] = [];
        $data['total'] = $total = count($values);
        $values = (new Batch($values))->sort(function ($a, $b) use ($sort, $with_sort) {
            $a = ($a_is_folder = is_dir($a)) ? new Folder($a) : new File($a);
            $b = ($b_is_folder = is_dir($b)) ? new Folder($b) : new File($b);
            if (!$with_sort) {
                // Folder first
                if ($a_is_folder !== $b_is_folder) {
                    return $a_is_folder ? -1 : 1;
                }
            }
            if (!isset($a->{$sort[1]}) || !isset($b->{$sort[1]})) {
                return 0;
            }
            return 1 === $sort[0] ? $a->{$sort[1]} <=> $b->{$sort[1]} : $b->{$sort[1]} <=> $a->{$sort[1]};
        });
        if ($with_limit) {
            $values = $values->limit($limit);
        } else {
            $values = $values->chunk($chunk, $part - 1);
        }
        foreach ($values as $v) {
            $ff = is_dir($v) ? new Folder($v) : new File($v);
            $rr = [];
            $rr['_seal'] = $ff->_seal;
            $rr['_time'] = $ff->_time;
            $rr['id'] = $ff->id;
            $rr['is']['blob'] = x\hub\is\blob($ff);
            $rr['is']['file'] = x\hub\is\file($ff);
            $rr['is']['folder'] = x\hub\is\folder($ff);
            $rr['is']['text'] = x\hub\is\text($ff);
            $rr['link'] = (string) $ff->link;
            $rr['name'] = $ff->name;
            $rr['route'] = $ff->route;
            $rr['seal'] = $ff->seal;
            $rr['time'] = (string) $ff->time;
            if (x\hub\is\file($ff)) {
                $rr['_size'] = $ff->_size;
                $rr['size'] = $ff->size;
                $rr['type'] = $ff->type;
                $rr['x'] = $ff->x;
            } else {
                $rr['_size'] = $rr['size'] = null; // Use `/hub/+/size/…`
            }
            ksort($rr);
            ksort($rr['is']);
            $data['children'][] = $rr;
        }
        $data['has']['children'] = $total > 0;
        $data['has']['next'] = $with_limit ? false : $part < ceil($total / $chunk);
        $data['has']['prev'] = $with_limit ? false : $part > 1;
        if ($with_limit) {
            $r['query']['limit'] = $limit;
        } else {
            $r['query']['chunk'] = $chunk;
            $r['query']['part'] = $part;
        }
        $r['query']['at'] = $at;
        $r['query']['deep'] = $deep;
        $r['query']['sort'] = $sort;
        $r['query']['x'] = $x;
    } else {
        $data['type'] = $f->type;
        $data['x'] = $f->x;
        if (array_intersect_key($_GET, [
            'at' => 1,
            'chunk' => 1,
            'deep' => 1,
            'limit' => 1,
            'part' => 1,
            'sort' => 1,
            'x' => 1
        ])) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
    }
    $data['is']['blob'] = x\hub\is\blob($f);
    $data['is']['file'] = x\hub\is\file($f);
    $data['is']['folder'] = x\hub\is\folder($f);
    $data['is']['text'] = x\hub\is\text($f);
    ksort($data);
    ksort($data['has']);
    ksort($data['is']);
    $r['data'] = $data;
    $r['description'] = i('Okay.');
    $r['status'] = 200;
    ksort($r);
    if (!empty($r['query'])) {
        ksort($r['query']);
    } else {
        $r['query'] = (object) []; // Force object in the JSON output
    }
    return $r;
}

if ('PATCH' === $q) {}

if ('POST' === $q) {}

if ('PUT' === $q) {
    $with_content = array_key_exists('content', $_REQUEST);
    $with_name = array_key_exists('name', $_REQUEST);
    $with_route = array_key_exists('route', $_REQUEST);
    $with_seal = array_key_exists('seal', $_REQUEST);
    $with_x = array_key_exists('x', $_REQUEST);
    $content = $with_content ? $_REQUEST['content'] : "";
    $name = $with_name ? $_REQUEST['name'] : "";
    $route = $with_route ? $_REQUEST['route'] : "";
    $seal = $with_seal ? $_REQUEST['seal'] : "";
    $x = $with_x ? $_REQUEST['x'] : "";
    if (!is_int($seal) && !is_string($seal)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if (!is_string($content) || !x\hub\is\name($name)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    if ("" !== $route && !x\hub\is\route($route)) {
        $r['description'] = i('Bad request.');
        $r['status'] = 400;
        return $r;
    }
    $route = trim($route);
    if (is_dir($path = path(PATH . D . $path))) {
        $path .= ("" !== $route ? D . $route : "") . D . $name;
        // Create a new file
        if ($with_content) {
            if ($with_x) {
                if (!x\hub\is\name($x)) {
                    $r['description'] = i('Bad request.');
                    $r['status'] = 400;
                    return $r;
                }
                $path .= '.' . $x;
            }
            if (is_dir($path)) {
                $r['description'] = i('Path already exists as a folder.');
                $r['status'] = 409;
                return $r;
            }
            if (is_file($path)) {
                $r['description'] = i('File already exists.');
                $r['status'] = 409;
                return $r;
            }
            if (false === file_put_contents($path, $content)) {
                $r['description'] = i('Internal server error.');
                $r['status'] = 500;
                return $r;
            }
            // Display the result
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $path = '/at/' . strtr(substr($path, strlen(PATH . D)), D, '/');
            $r = require __FILE__;
            $r['description'] = i('Created.');
            $r['status'] = 201;
            return $r;
        }
        // A `PUT` request to a folder entity without the `content` field is a request to create a folder
        if ($with_x) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
        if (is_dir($path)) {
            $r['description'] = i('Folder already exists.');
            $r['status'] = 409;
            return $r;
        }
        if (is_file($path)) {
            $r['description'] = i('Path already exists as a file.');
            $r['status'] = 409;
            return $r;
        }
        if (!mkdir($path, 0775, true)) {
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        // Display the result
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $path = '/at/' . strtr(substr($path, strlen(PATH . D)), D, '/');
        $r = require __FILE__;
        $r['description'] = i('Created.');
        $r['status'] = 201;
        return $r;
    }
    if (is_file($path)) {
        $r['description'] = i('Bad request.'); // Use `PATCH` to modify a file
        $r['status'] = 400;
        return $r;
    }
    $r['description'] = i('File or folder does not exist.');
    $r['status'] = 404;
    return $r;
}

$r['description'] = i('Method not allowed.');
$r['status'] = 405;

return $r;