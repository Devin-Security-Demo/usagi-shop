<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$cart = cart();

if (is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);
    $qty = (int) ($_POST['qty'] ?? 1);

    $stmt = db()->prepare('SELECT id, name, stock FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        $current = $cart->items()[$productId] ?? 0;
        $stock = (int) $product['stock'];
        if ($action === 'add') {
            if ($stock === 0) {
                flash('warning', '申し訳ありません。「' . $product['name'] . '」は在庫切れです。');
            } else {
                $cart->set($productId, min($stock, $current + max(1, $qty)));
                flash('success', '「' . $product['name'] . '」をカートに追加しました。');
            }
        } elseif ($action === 'update') {
            $cart->set($productId, min($stock, $qty));
        } elseif ($action === 'remove') {
            $cart->remove($productId);
            flash('info', '「' . $product['name'] . '」をカートから削除しました。');
        }
        save_cart($cart);
    }
    redirect('/cart.php');
}

$lines = [];
if (!$cart->isEmpty()) {
    $ids = array_keys($cart->items());
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = array_column($stmt->fetchAll(), null, 'id');
    $lines = $cart->lines($products);
}
$subtotal = \Usagi\Cart::subtotal($lines);
$threshold = (int) config('free_shipping_threshold');

render('header', ['title' => 'ショッピングカート', 'active' => 'cart']);
?>
<div class="container py-4">
  <h1 class="section-title mb-4">ショッピングカート</h1>
  <?php if (!$lines): ?>
  <div class="panel text-center py-5">
    <p class="mb-3">カートに商品が入っていません。</p>
    <a href="/" class="btn btn-primary px-4">お買い物を続ける</a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="panel p-0 overflow-hidden">
        <table class="table table-usagi mb-0">
          <thead><tr><th class="ps-4">商品</th><th class="text-end">単価</th><th class="text-center" style="width:130px">数量</th><th class="text-end">小計</th><th class="pe-4"></th></tr></thead>
          <tbody>
            <?php foreach ($lines as $line): ?>
            <tr>
              <td class="ps-4">
                <div class="d-flex align-items-center gap-3">
                  <img src="<?= h($line['product']['image']) ?>" class="thumb-xs" alt="">
                  <a href="/product.php?id=<?= (int) $line['product_id'] ?>" class="fw-semibold text-body"><?= h($line['name']) ?></a>
                </div>
              </td>
              <td class="text-end"><?= yen($line['price']) ?></td>
              <td>
                <form method="post" action="/cart.php" class="cart-qty-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                  <input type="number" name="qty" class="form-control form-control-sm text-center" value="<?= (int) $line['qty'] ?>" min="1" max="<?= max(1, min(20, (int) $line['product']['stock'])) ?>">
                </form>
              </td>
              <td class="text-end fw-semibold"><?= yen($line['line_total']) ?></td>
              <td class="pe-4 text-end">
                <form method="post" action="/cart.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="product_id" value="<?= (int) $line['product_id'] ?>">
                  <button class="btn btn-link btn-sm text-danger p-0" type="submit">削除</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a href="/" class="btn btn-link mt-3 px-0">← お買い物を続ける</a>
    </div>
    <div class="col-lg-4">
      <div class="panel">
        <h2 class="h6 fw-bold mb-3">ご注文内容</h2>
        <div class="summary-row"><span>商品小計（<?= $cart->count() ?>点）</span><span><?= yen($subtotal) ?></span></div>
        <div class="summary-row text-muted small"><span>送料</span><span>次の画面で計算されます</span></div>
        <?php if ($subtotal < $threshold): ?>
        <div class="alert alert-warning small py-2 mt-2 mb-0">あと <?= yen($threshold - $subtotal) ?> で送料無料！</div>
        <?php else: ?>
        <div class="alert alert-success small py-2 mt-2 mb-0">送料無料の対象です（沖縄を除く）</div>
        <?php endif; ?>
        <div class="summary-row summary-total"><span>合計（税込）</span><span><?= yen($subtotal) ?></span></div>
        <a href="/checkout.php" class="btn btn-primary w-100 py-2 mt-3">レジに進む</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php render('footer'); ?>
