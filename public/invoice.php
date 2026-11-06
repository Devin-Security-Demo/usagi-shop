<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();

$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT);
if (!$orderId) {
    not_found();
}

$stmt = db()->prepare('SELECT invoice_file FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$invoiceFile = $stmt->fetchColumn();
if (!is_string($invoiceFile) || $invoiceFile === '') {
    not_found();
}

$file = basename($invoiceFile);
$dir = realpath(APP_ROOT . '/storage/invoices');
$path = realpath(APP_ROOT . '/storage/invoices/' . $file);
if ($dir === false || $path === false || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
