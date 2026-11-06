<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();

$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT);
if (!$orderId) {
    not_found();
}

$stmt = db()->prepare('SELECT invoice_file FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, (int) $user['id']]);
$file = $stmt->fetchColumn();
if (!is_string($file) || $file === '') {
    not_found();
}

$dir = realpath(APP_ROOT . '/storage/invoices');
$path = realpath($dir . '/' . basename($file));
if ($dir === false || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
