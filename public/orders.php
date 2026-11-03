<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();

$stmt = db()->prepare('SELECT o.*, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) AS item_count FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

render('header', ['title' => 'マイページ']);
?>
<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <img src="<?= h(avatar_url($user)) ?>" class="avatar-lg" alt="">
    <div>
      <div class="text-muted small">マイページ</div>
      <h1 class="h4 fw-bold mb-1"><?= h($user['name']) ?> さん</h1>
      <a href="/account.php" class="small">アカウント設定 →</a>
    </div>
  </div>

  <h2 class="section-title mb-3">注文履歴</h2>
  <?php if (!$orders): ?>
  <div class="panel text-center py-5"><p class="mb-3">まだご注文はありません。</p><a href="/" class="btn btn-primary">お買い物をはじめる</a></div>
  <?php else: ?>
  <div class="panel p-0 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-usagi mb-0">
        <thead><tr><th class="ps-4">注文番号</th><th>注文日</th><th class="text-center">点数</th><th class="text-end">合計金額</th><th class="text-center">ステータス</th><th class="text-center">請求書</th><th class="pe-4"></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="ps-4 fw-semibold"><?= h($o['order_no']) ?></td>
            <td><?= h(format_date($o['created_at'], 'Y/m/d H:i')) ?></td>
            <td class="text-center"><?= (int) $o['item_count'] ?></td>
            <td class="text-end"><?= yen($o['total']) ?></td>
            <td class="text-center"><?= order_status_badge($o['status']) ?></td>
            <td class="text-center">
              <?php if ($o['invoice_file']): ?>
              <a class="btn btn-sm btn-outline-primary" href="/invoice.php?file=<?= urlencode($o['invoice_file']) ?>">請求書PDF</a>
              <?php else: ?><span class="small text-muted">発送後に発行</span><?php endif; ?>
            </td>
            <td class="pe-4 text-end"><a href="/order.php?id=<?= (int) $o['id'] ?>" class="btn btn-sm btn-light">詳細</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php render('footer'); ?>
