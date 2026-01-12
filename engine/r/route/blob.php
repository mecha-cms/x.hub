<?php

if (is_int($status = x\hub\status())) {
    return ['status' => $status];
}

if ('GET' !== $_SERVER['REQUEST_METHOD']) {
    return ['status' => 405];
}

$accept = status()[1]['accept'] ?? P;
$path = substr($path, 6); // `strlen('/blob/')`

if (is_string($name = $_GET['name'] ?? $path)) {
    $name = basename($name);
}

if (!(is_string($path) && "" !== $path)) {
    return ['status' => 400];
}

if (!(is_string($name) && "" !== $name)) {
    return ['status' => 400];
}

if (!($path = path(LOT . D . $path))) {
    return ['status' => 404];
}

if (false === ($type = mime_content_type($path))) {
    return ['status' => 406];
}

if (is_file($path)) {
    if (in_array($type, [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp'
    ])) {
        if (!extension_loaded('gd')) {
            return ['status' => 424]; // Missing GD extension
        }
        $w = $_GET['width'] ?? $_GET['w'] ?? -1;
        $h = $_GET['height'] ?? $_GET['h'] ?? $w;
        if (is_int($h) && is_int($w) && $h > 0 && $w > 0) {
            // Rename
            if (!array_key_exists('name', $_GET)) {
                $name = pathinfo($name, PATHINFO_FILENAME) . '-' . $w . ($h === $w ? "" : 'x' . $h) . '.' . pathinfo($name, PATHINFO_EXTENSION);
            }
            if (false === ($info = getimagesize($path))) {
                return ['status' => 415]; // Unsupported media type
            }
            [$width, $height] = $info;
            if (!function_exists($task = 'imagecreatefrom' . substr($type, 6))) {
                return ['status' => 415]; // Unsupported media type
            }
            $blob = call_user_func($task, $path);
            $scale = max($w / $width, $h / $height);
            $scale_h = (int) ceil($height * $scale);
            $scale_w = (int) ceil($width * $scale);
            $blob_scale = imagecreatetruecolor($scale_w, $scale_h);
            if (in_array($type, ['image/gif', 'image/png', 'image/webp'], true)) {
                imagealphablending($blob_scale, false);
                imagesavealpha($blob_scale, true);
                imagefilledrectangle($blob_scale, 0, 0, $scale_w, $scale_h, imagecolorallocatealpha($blob_scale, 0, 0, 0, 127));
            }
            imagecopyresampled($blob_scale, $blob, 0, 0, 0, 0, $scale_w, $scale_h, $width, $height);
            $crop_x = (int) floor(($scale_w - $w) / 2);
            $crop_y = (int) floor(($scale_h - $h) / 2);
            $blob_crop = imagecreatetruecolor($w, $h);
            if (in_array($type, ['image/gif', 'image/png', 'image/webp'], true)) {
                imagealphablending($blob_crop, false);
                imagesavealpha($blob_crop, true);
                imagefilledrectangle($blob_crop, 0, 0, $w, $h, imagecolorallocatealpha($blob_crop, 0, 0, 0, 127));
            }
            imagecopy($blob_crop, $blob_scale, 0, 0, $crop_x, $crop_y, $w, $h);
            status(200, [
                'cache-control' => 'public, max-age=86400',
                'content-disposition' => 'inline; filename="' . $name . '"'
            ]);
            if (!function_exists($task = 'image' . substr($type, 6))) {
                return ['status' => 415]; // Unsupported media type
            }
            type($type);
            if ('image/gif' === $type) {
                imagegif($blob_crop);
            } else if ('image/jpeg' === $type) {
                imagejpeg($blob_crop, null, 90);
            } else if ('image/png' === $type) {
                imagepng($blob_crop);
            } else if ('image/webp' === $type) {
                imagewebp($blob_crop, null, 90);
            } else {}
            imagedestroy($blob);
            imagedestroy($blob_crop);
            imagedestroy($blob_scale);
            exit;
        }
    }
    if (P === $accept || '*/*' === $accept) {
        status(200, ['content-disposition' => 'inline; filename="' . $name . '"']);
        type($type);
        echo content($path);
        exit;
    }
    if ('text/plain' === $accept) {
        status(200, ['content-disposition' => 'inline; filename="' . $name . '.txt"']);
        type($accept);
        echo content($path);
        exit;
    }
}

if ('application/zip' === $accept) {
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

return ['status' => 406]; // Not acceptable