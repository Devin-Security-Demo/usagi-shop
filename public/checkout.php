<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

use Usagi\Cart;
use Usagi\OrderMailer;
use Usagi\ShippingCalculator;
use Usagi\ShippingClient;

$user = require_login();

if (isset($_GET['done']) && is_string($_GET['done'])) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_no = ? AND user_id = ?');
    $stmt->execute([$_GET['done'], $user['id']]);
    $order = $stmt->fetch();
    if (!$order) {
        not_found();
    }
    render('header', ['title' => 'ご注文完了']);
    ?>
<div class="container py-5">
  <div class="panel text-center py-5 mx-auto" style="max-width:640px">
    <div class="order-done-icon mb-4">✓</div>
    <h1 class="h4 fw-bold mb-3">ご注文ありがとうございました</h1>
    <p class="text-muted mb-1">ご注文番号</p>
    <p class="order-number mb-4"><?= h($order['order_no']) ?></p>
    <p class="small mb-4">ご登録のメールアドレス（<?= h($user['email']) ?>）に注文確認メールをお送りしました。<br>商品の発送準備が整いましたら、あらためてご連絡いたします。</p>
    <div class="d-flex justify-content-center gap-3">
      <a href="/order.php?id=<?= (int) $order['id'] ?>" class="btn btn-outline-primary">注文内容を確認する</a>
      <a href="/" class="btn btn-primary">お買い物を続ける</a>
    </div>
  </div>
</div>
    <?php
    render('footer');
    exit;
}

$cart = cart();
if ($cart->isEmpty()) {
    redirect('/cart.php');
}

$ids = array_keys($cart->items());
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$products = array_column($stmt->fetchAll(), null, 'id');
$lines = $cart->lines($products);
$subtotal = Cart::subtotal($lines);

$form = [
    'ship_name'    => $user['name'],
    'ship_zip'     => $user['zip'] ?? '',
    'ship_address' => $user['address'] ?? '',
    'ship_phone'   => $user['phone'] ?? '',
    'payment'      => '代金引換',
    'note'         => '',
];
$errors = [];
$payments = ['代金引換', '銀行振込'];

if (is_post()) {
    csrf_verify();
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    if ($form['ship_name'] === '' || mb_strlen($form['ship_name']) > 100) {
        $errors[] = 'お名前を入力してください。';
    }
    try {
        $form['ship_zip'] = ShippingCalculator::normalizeZip($form['ship_zip']);
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }
    if ($form['ship_address'] === '' || mb_strlen($form['ship_address']) > 255) {
        $errors[] = 'ご住所を入力してください。';
    }
    if (!preg_match('/\A[0-9\-]{10,13}\z/', $form['ship_phone'])) {
        $errors[] = '電話番号を正しく入力してください。';
    }
    if (!in_array($form['payment'], $payments, true)) {
        $errors[] = 'お支払い方法を選択してください。';
    }
    if (mb_strlen($form['note']) > 500) {
        $errors[] = '備考は500文字以内で入力してください。';
    }

    $shippingFee = null;
    if (!$errors) {
        try {
            $quote = (new ShippingClient(config('shipping_api')))->quote($form['ship_zip'], $subtotal);
            $shippingFee = (int) $quote['fee'];
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage() . '時間をおいて再度お試しください。';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT id, name, stock FROM products WHERE id IN ($placeholders) FOR UPDATE");
            $lock->execute($ids);
            $stocks = array_column($lock->fetchAll(), null, 'id');
            foreach ($lines as $line) {
                if ((int) $stocks[$line['product_id']]['stock'] < $line['qty']) {
                    throw new DomainException('「' . $line['name'] . '」の在庫が不足しています。数量を変更してください。');
                }
            }

            $total = $subtotal + $shippingFee;
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, status, subtotal, shipping_fee, total, payment_method, ship_name, ship_zip, ship_address, ship_phone, note) VALUES (?, \'processing\', ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $subtotal, $shippingFee, $total, $form['payment'], $form['ship_name'], $form['ship_zip'], $form['ship_address'], $form['ship_phone'], $form['note'] !== '' ? $form['note'] : null]);
            $orderId = (int) $pdo->lastInsertId();
            $orderNo = sprintf('UO-%06d', 100230 + $orderId);
            $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?')->execute([$orderNo, $orderId]);

            $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty) VALUES (?, ?, ?, ?, ?)');
            $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($lines as $line) {
                $item->execute([$orderId, $line['product_id'], $line['name'], $line['price'], $line['qty']]);
                $dec->execute([$line['qty'], $line['product_id']]);
            }
            $pdo->commit();
        } catch (DomainException $e) {
            $pdo->rollBack();
            flash('warning', $e->getMessage());
            redirect('/cart.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $order = ['order_no' => $orderNo, 'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $total] + $form;
        (new OrderMailer(config('mail')))->sendConfirmation($order, $lines, $user['email'], $user['name']);

        $cart->clear();
        save_cart($cart);
        redirect('/checkout.php?done=' . urlencode($orderNo));
    }
}

render('header', ['title' => 'ご注文手続き']);
?>
<div class="container py-4">
  <h1 class="section-title mb-4">ご注文手続き</h1>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger small py-2"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" action="/checkout.php">
    <?= csrf_field() ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="panel mb-4">
          <h2 class="h6 fw-bold mb-3">お届け先</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="ship_name">お名前</label>
              <input class="form-control" id="ship_name" name="ship_name" value="<?= h($form['ship_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="ship_phone">電話番号</label>
              <input class="form-control" id="ship_phone" name="ship_phone" value="<?= h($form['ship_phone']) ?>" placeholder="090-1234-5678" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="ship_zip">郵便番号</label>
              <input class="form-control" id="ship_zip" name="ship_zip" value="<?= h($form['ship_zip']) ?>" placeholder="150-0001" required>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold" for="ship_address">ご住所</label>
              <input class="form-control" id="ship_address" name="ship_address" value="<?= h($form['ship_address']) ?>" required>
            </div>
          </div>
        </div>
        <div class="panel">
          <h2 class="h6 fw-bold mb-3">お支払い方法</h2>
          <?php foreach ($payments as $i => $method): ?>
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment" id="payment<?= $i ?>" value="<?= h($method) ?>" <?= $form['payment'] === $method ? 'checked' : '' ?>>
            <label class="form-check-label" for="payment<?= $i ?>"><?= h($method) ?></label>
          </div>
          <?php endforeach; ?>
          <label class="form-label small fw-semibold mt-3" for="note">備考（任意）</label>
          <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" placeholder="お届け時間のご希望など"><?= h($form['note']) ?></textarea>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="panel" id="checkout-summary" data-subtotal="<?= (int) $subtotal ?>">
          <h2 class="h6 fw-bold mb-3">ご注文内容</h2>
          <table class="table table-usagi table-sm small">
            <thead><tr><th>商品</th><th class="text-center">数量</th><th class="text-end">金額</th></tr></thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
              <tr><td><?= h($line['name']) ?></td><td class="text-center"><?= (int) $line['qty'] ?></td><td class="text-end"><?= yen($line['line_total']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="summary-row"><span>商品小計</span><span><?= yen($subtotal) ?></span></div>
          <div class="summary-row"><span>送料<small class="text-muted" id="shipping-region"></small></span><span id="shipping-fee">—</span></div>
          <div class="summary-row text-muted small"><span>うち消費税（10%）</span><span><?= yen(Cart::includedTax($subtotal)) ?></span></div>
          <div class="summary-row summary-total"><span>お支払い合計</span><span id="order-total"><?= yen($subtotal) ?></span></div>
          <button type="submit" class="btn btn-primary w-100 py-2 mt-3">注文を確定する</button>
          <p class="small text-muted mt-2 mb-0">送料はお届け先の郵便番号から自動で計算されます。</p>
        </div>
      </div>
    </div>
  </form>
</div>
<?php render('footer'); ?>
