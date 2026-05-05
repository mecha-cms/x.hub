<?php

if (($r = x\hub\r())['status'] >= 400) {
    if (defined('TEST') && TEST) {} else {
        unset($r['?']);
    }
    return $r;
}

$r['user'] = x\hub\user($r['?']);

if (defined('TEST') && TEST) {} else {
    unset($r['?']);
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = i('Method not allowed.');
    $r['status'] = 405;
    return $r;
}

$path = substr(rawurldecode($path), 6); // `strlen('/blob/')`

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (!($path = path(PATH . D . $path))) {
    $r['description'] = i('File does not exist.');
    $r['status'] = 404;
    return $r;
}

$cache_try = static function ($tag_e, $time_m) {
    status([
        'etag' => $tag_e,
        'last-modified' => gmdate('D, d M Y H:i:s', $time_m) . ' GMT'
    ]);
    // <https://www.rfc-editor.org/rfc/rfc9111#section-4.3.2-4>
    if ($if_none_match = status()[2]['if-none-match'] ?? 0) {
        foreach (explode(',', $if_none_match) as $v) {
            // Match with `"asdf"` or `W/"asdf"`
            if ('*' === ($v = trim($v)) || $tag_e === $v || substr($tag_e, 2) === $v) {
                status(304);
                exit;
            }
        }
    }
    if ($if_modified_since = status()[2]['if-modified-since'] ?? 0) {
        if (false !== ($time_s = strtotime($if_modified_since)) && $time_s >= $time_m) {
            status(304);
            exit;
        }
    }
};

$accept = status()[1]['accept'] ?? 0;

if (is_string($name = $_GET['name'] ?? $path)) {
    $name = basename($name);
}

if (!(is_string($name) && "" !== $name)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

if (is_file($path)) {
    if (false === ($type = mime_content_type($path))) {
        $r['description'] = i('Not acceptable.');
        $r['status'] = 406;
        return $r;
    }
    $tag_e = 'W/"' . dechex($time_m = filemtime($path)) . '-' . dechex(filesize($path)) . '"';
    if (false !== strpos(',image/gif,image/jpeg,image/png,image/webp,', ',' . $type . ',')) {
        if (!extension_loaded('gd')) {
            $r['description'] = i('Missing PHP `gd` extension.');
            $r['status'] = 424;
            return $r;
        }
        $w = $_GET['width'] ?? $_GET['w'] ?? -1;
        $h = $_GET['height'] ?? $_GET['h'] ?? $w;
        if (is_int($h) && is_int($w) && $h > 0 && $w > 0) {
            $cache_try(substr($tag_e, 0, -1) . '-' . $w . ($h === $w ? "" : 'x' . $h) . '"', $time_m);
            // If the `name` option is not specified, rename the image file when its width and height are modified
            if (!array_key_exists('name', $_GET)) {
                $name = pathinfo($name, PATHINFO_FILENAME) . '-' . $w . ($h === $w ? "" : 'x' . $h) . '.' . pathinfo($name, PATHINFO_EXTENSION);
            }
            if (false === ($info = getimagesize($path))) {
                status([
                    'etag' => false,
                    'last-modified' => false
                ]);
                $r['description'] = i('Unsupported media type.');
                $r['status'] = 415;
                return $r;
            }
            [$w_max, $h_max] = $info;
            if (!function_exists($task = 'imagecreatefrom' . substr($type, 6))) {
                status([
                    'etag' => false,
                    'last-modified' => false
                ]);
                $r['description'] = i('Unsupported media type.');
                $r['status'] = 415;
                return $r;
            }
            $blob = call_user_func($task, $path);
            $scale = max($w / $w_max, $h / $h_max);
            $h_scale = (int) ceil($h_max * $scale);
            $w_scale = (int) ceil($w_max * $scale);
            $blob_scale = imagecreatetruecolor($w_scale, $h_scale);
            if (false !== strpos(',image/gif,image/png,image/webp,', ',' . $type . ',')) {
                imagealphablending($blob_scale, false);
                imagesavealpha($blob_scale, true);
                imagefilledrectangle($blob_scale, 0, 0, $w_scale, $h_scale, imagecolorallocatealpha($blob_scale, 0, 0, 0, 127));
            }
            imagecopyresampled($blob_scale, $blob, 0, 0, 0, 0, $w_scale, $h_scale, $w_max, $h_max);
            $x_crop = (int) floor(($w_scale - $w) / 2);
            $y_crop = (int) floor(($h_scale - $h) / 2);
            $blob_crop = imagecreatetruecolor($w, $h);
            if (false !== strpos(',image/gif,image/png,image/webp,', ',' . $type . ',')) {
                imagealphablending($blob_crop, false);
                imagesavealpha($blob_crop, true);
                imagefilledrectangle($blob_crop, 0, 0, $w, $h, imagecolorallocatealpha($blob_crop, 0, 0, 0, 127));
            }
            imagecopy($blob_crop, $blob_scale, 0, 0, $x_crop, $y_crop, $w, $h);
            if (!function_exists($task = 'image' . substr($type, 6))) {
                status([
                    'etag' => false,
                    'last-modified' => false
                ]);
                $r['description'] = i('Unsupported media type.');
                $r['status'] = 415;
                return $r;
            }
            status(200, [
                'content-disposition' => 'inline; filename="' . $name . '"',
                'content-type' => $type
            ]);
            if ('image/gif' === $type) {
                imagegif($blob_crop);
            } else if ('image/jpeg' === $type) {
                imagejpeg($blob_crop, null, 90);
            } else if ('image/png' === $type) {
                imagepng($blob_crop);
            } else if ('image/webp' === $type) {
                imagewebp($blob_crop, null, 90);
            } else {}
            // imagedestroy($blob);
            // imagedestroy($blob_crop);
            // imagedestroy($blob_scale);
            exit;
        }
    }
    $cache_try($tag_e, $time_m);
    if (0 === $accept || '*/*' === $accept) {
        status(200, [
            'content-disposition' => 'inline; filename="' . $name . '"',
            'content-type' => $type
        ]);
        echo content($path);
        exit;
    }
    if ('text/plain' === $accept) {
        status(200, [
            'content-disposition' => 'inline; filename="' . $name . '.txt"',
            'content-type' => $accept
        ]);
        echo content($path);
        exit;
    }
}

if ('application/zip' === $accept) {
    if (is_dir($path)) {
        $size = $time_m = 0;
        foreach (g($path, null, true) as $k => $v) {
            if (1 === $v) {
                $size += filesize($k);
                $time_m = max($time_m, filemtime($k));
            }
        }
        $tag_e = 'W/"' . dechex($time_m) . '-' . dechex($size) . '"';
    } else {
        $tag_e = 'W/"' . dechex($time_m = filemtime($path)) . '-' . dechex(filesize($path)) . '"';
    }
    $cache_try($tag_e, $time_m);
    status(200);
    require __DIR__ . D . '..' . D . 'vendor' . D . 'autoload.php';
    $zip = new ZipStream\ZipStream(outputName: $name . '.zip', sendHttpHeaders: true);
    if (is_dir($path)) {
        foreach (g($path, null, true) as $k => $v) {
            $kk = substr($k, strlen($path . D));
            if (0 === $v) {
                $zip->addDirectory(fileName: $kk);
            } else {
                $zip->addFileFromPath(fileName: $kk, path: $k);
            }
        }
    } else {
        $zip->addFileFromPath(fileName: $name, path: $path);
    }
    $zip->finish();
    exit;
}

$r['description'] = i('Not acceptable.');
$r['status'] = 406;

return $r;