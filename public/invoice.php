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

$file = basename($file);
if (!preg_match('/\A[A-Z0-9-]+\.pdf\z/', $file)) {
    not_found();
}

$path = APP_ROOT . '/storage/invoices/' . $file;
if (!is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
