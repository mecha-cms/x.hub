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

if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    $r['description'] = i('Method not allowed.');
    $r['status'] = 405;
    return $r;
}

$path = substr(rawurldecode($path), 10); // `strlen('/set/blob/')`

if (!(is_string($path) && "" !== $path)) {
    $r['description'] = i('Bad request.');
    $r['status'] = 400;
    return $r;
}

// If `$path` is a folder, assume the incoming blob has its name already
if (is_dir($path)) {
    // Blob only
    if ('multipart/form-data' === ($type = type())) {
        $blob = $_FILES['blob'] ?? [];
        if (!is_array($blob = $_FILES['blob'] ?? 0)) {
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        // Validate blob
        if (UPLOAD_ERR_OK !== ($blob['error'] ?? UPLOAD_ERR_NO_FILE)) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
        // TODO: Check disk space
        if (is_string($name = $blob['name'] ?? 0)) {
            $name = basename($name);
        }
        if (!(is_string($name) && "" !== $name)) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
        if (is_file($f = $path . D . $name)) {
            $r['description'] = i('File already exists.');
            $r['status'] = 409;
            return $r;
        }
        if (false === ($ff = tempnam($path, '.blob-'))) {
            $r['description'] = i('Insufficient storage.');
            $r['status'] = 507;
            return $r;
        }
        if (!move_uploaded_file($blob['tmp_name'], $ff)) {
            unlink($ff);
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        if (!rename($ff, $f)) {
            unlink($ff);
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        // Display the result
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $path = '/data/' . strtr(substr($f, strlen(LOT . D)), D, '/');
        $r = require __DIR__ . D  . 'data.php';
        $r['description'] = i('File successfully created.');
        $r['status'] = 201;
        return $r;
    }
    // Text only
    if ('application/x-www-form-urlencoded' === $type) {
        if (is_string($name = $_POST['name'] ?? 0)) {
            $name = basename($name);
        }
        if (!(is_string($name) && "" !== $name)) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
        if (is_file($f = $path . D . $name)) {
            $r['description'] = i('File already exists.');
            $r['status'] = 409;
            return $r;
        }
        // TODO: Check disk space
        if (is_int(file_put_contents($f, s($_POST['content'] ?? "")))) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $path = '/data/' . strtr(substr($f, strlen(LOT . D)), D, '/');
            $r = require __DIR__ . D  . 'data.php';
            $r['description'] = i('File successfully created.');
            $r['status'] = 201;
            return $r;
        }
        $r['description'] = i('Internal server error.');
        $r['status'] = 500;
        return $r;
    }
    $r['description'] = i('Unsupported media type.');
    $r['status'] = 415;
    return $r;
}

if (is_file($path)) {
    // Blob only
    if ('multipart/form-data' === ($type = type())) {
        $blob = $_FILES['blob'] ?? [];
        if (!is_array($blob = $_FILES['blob'] ?? 0)) {
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        // Validate blob
        if (UPLOAD_ERR_OK !== ($blob['error'] ?? UPLOAD_ERR_NO_FILE)) {
            $r['description'] = i('Bad request.');
            $r['status'] = 400;
            return $r;
        }
        // TODO: Check disk space
        if (false === ($ff = tempnam(dirname($f = $path), '.blob-'))) {
            $r['description'] = i('Insufficient storage.');
            $r['status'] = 507;
            return $r;
        }
        if (!move_uploaded_file($blob['tmp_name'], $ff)) {
            unlink($ff);
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        if (!unlink($f)) {
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        if (!rename($ff, $f)) {
            unlink($ff);
            $r['description'] = i('Internal server error.');
            $r['status'] = 500;
            return $r;
        }
        // Display the result
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $path = '/data/' . strtr(substr($f, strlen(LOT . D)), D, '/');
        $r = require __DIR__ . D  . 'data.php';
        $r['description'] = i('File successfully created.');
        $r['status'] = 201;
        return $r;
    }
    // Text only
    if ('application/x-www-form-urlencoded' === $type) {
        // TODO: Check disk space
        if (is_int(file_put_contents($f = $path, s($_POST['content'] ?? "")))) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $path = '/data/' . strtr(substr($f, strlen(LOT . D)), D, '/');
            $r = require __DIR__ . D  . 'data.php';
            $r['description'] = i('File successfully created.');
            $r['status'] = 201;
            return $r;
        }
        $r['description'] = i('Internal server error.');
        $r['status'] = 500;
        return $r;
    }
    $r['description'] = i('Unsupported media type.');
    $r['status'] = 415;
    return $r;
}

$r['description'] = i('Bad request.');
$r['status'] = 400;

return $r;