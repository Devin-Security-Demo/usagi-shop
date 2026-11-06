<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();

$id = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

$stmt = db()->prepare('SELECT invoice_file FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$file = $stmt->fetchColumn();
if (!is_string($file) || $file === '' || $file !== basename($file)) {
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
