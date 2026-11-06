<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

use Usagi\Cart;

$user = require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    not_found();
}

$stmt = db()->prepare('SELECT i.*, p.image FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ? ORDER BY i.id');
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();

render('header', ['title' => '注文詳細 ' . $order['order_no']]);
?>
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/orders.php">マイページ</a></li>
      <li class="breadcrumb-item active" aria-current="page">注文詳細</li>
    </ol>
  </nav>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <h1 class="h4 fw-bold mb-1">注文番号 <?= h($order['order_no']) ?></h1>
      <div class="small text-muted">ご注文日時：<?= h(format_date($order['created_at'], 'Y年n月j日 H:i')) ?></div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <?= order_status_badge($order['status']) ?>
      <?php if ($order['invoice_file']): ?>
      <a class="btn btn-primary" href="/invoice.php?id=<?= (int) $order['id'] ?>">請求書PDFダウンロード</a>
      <?php else: ?>
      <button class="btn btn-outline-secondary" disabled>請求書は発送後に発行されます</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="panel p-0 overflow-hidden">
        <table class="table table-usagi mb-0">
          <thead><tr><th class="ps-4">商品</th><th class="text-end">単価</th><th class="text-center">数量</th><th class="text-end pe-4">小計</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td class="ps-4"><div class="d-flex align-items-center gap-3"><img src="<?= h($item['image']) ?>" class="thumb-xs" alt=""><span class="fw-semibold"><?= h($item['product_name']) ?></span></div></td>
              <td class="text-end"><?= yen($item['unit_price']) ?></td>
              <td class="text-center"><?= (int) $item['qty'] ?></td>
              <td class="text-end pe-4"><?= yen($item['unit_price'] * $item['qty']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="panel mb-4">
        <h2 class="h6 fw-bold mb-3">お支払い</h2>
        <div class="summary-row"><span>商品小計</span><span><?= yen($order['subtotal']) ?></span></div>
        <div class="summary-row"><span>送料</span><span><?= (int) $order['shipping_fee'] === 0 ? '無料' : yen($order['shipping_fee']) ?></span></div>
        <div class="summary-row text-muted small"><span>うち消費税（10%）</span><span><?= yen(Cart::includedTax((int) $order['subtotal'])) ?></span></div>
        <div class="summary-row summary-total"><span>合計</span><span><?= yen($order['total']) ?></span></div>
        <div class="small text-muted mt-2">お支払い方法：<?= h($order['payment_method']) ?></div>
      </div>
      <div class="panel">
        <h2 class="h6 fw-bold mb-3">お届け先</h2>
        <p class="small mb-1">〒<?= h($order['ship_zip']) ?></p>
        <p class="small mb-1"><?= h($order['ship_address']) ?></p>
        <p class="small mb-1"><?= h($order['ship_name']) ?> 様</p>
        <p class="small mb-0">TEL <?= h($order['ship_phone']) ?></p>
        <?php if ($order['shipped_at']): ?><p class="small text-muted mt-2 mb-0">発送日：<?= h(format_date($order['shipped_at'])) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php render('footer'); ?>
