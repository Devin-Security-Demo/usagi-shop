<?php

require dirname(__DIR__, 2) . '/lib/bootstrap.php';

require_admin();

$rows = db()->query(
    'SELECT o.order_no, o.created_at, u.name AS customer, u.email, o.status, o.payment_method,
            o.subtotal, o.shipping_fee, o.total, o.ship_zip, o.ship_address
       FROM orders o JOIN users u ON u.id = o.user_id
      ORDER BY o.created_at DESC'
)->fetchAll();

$cell = static function ($value): string {
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $value = "'" . $value;
    }
    return $value;
};

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['注文番号', '注文日時', '顧客名', 'メールアドレス', 'ステータス', '支払方法', '小計', '送料', '合計', '郵便番号', '住所'], ',', '"', '');
foreach ($rows as $r) {
    fputcsv($out, array_map($cell, [
        $r['order_no'], $r['created_at'], $r['customer'], $r['email'], order_status_label($r['status']),
        $r['payment_method'], $r['subtotal'], $r['shipping_fee'], $r['total'], $r['ship_zip'], $r['ship_address'],
    ]), ',', '"', '');
}
fclose($out);
