<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

require_login();

$file = isset($_GET['file']) && is_string($_GET['file']) ? $_GET['file'] : '';
if ($file === '') {
    not_found();
}

$path = APP_ROOT . '/storage/invoices/' . $file;
if (!is_file($path)) {
    not_found();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
