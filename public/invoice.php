<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

$stmt = db()->prepare('SELECT invoice_file FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$invoiceFile = $stmt->fetchColumn();
if (!is_string($invoiceFile) || $invoiceFile === '') {
    not_found();
}

$file = basename($invoiceFile);
$path = APP_ROOT . '/storage/invoices/' . $file;
if (!is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
