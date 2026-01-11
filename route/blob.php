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
    require __DIR__ . D . '..' . D . 'engine' . D . 'r' . D . 'vendor' . D . 'autoload.php';
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

return ['status' => 406];