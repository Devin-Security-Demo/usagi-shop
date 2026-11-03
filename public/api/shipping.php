<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/lib/helpers.php';

use Usagi\ShippingCalculator;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$zip = isset($_GET['zip']) && is_string($_GET['zip']) ? $_GET['zip'] : '';
$subtotal = max(0, (int) ($_GET['subtotal'] ?? 0));
$calc = new ShippingCalculator((int) config('free_shipping_threshold'));

try {
    $normalized = ShippingCalculator::normalizeZip($zip);
    $region = $calc->regionFor($normalized);
    echo json_encode([
        'zip'            => $normalized,
        'region'         => $region,
        'region_label'   => $calc->regionLabel($region),
        'base_fee'       => $calc->baseFee($region),
        'fee'            => $calc->fee($normalized, $subtotal),
        'free_threshold' => $calc->freeThreshold(),
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
