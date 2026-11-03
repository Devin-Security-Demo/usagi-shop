<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    not_found();
}

$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    not_found();
}

$user = current_user();
$errors = [];
$form = ['nickname' => $user['name'] ?? '', 'rating' => 5, 'body' => ''];

if (is_post()) {
    csrf_verify();
    $user = require_login();
    $form = [
        'nickname' => trim((string) ($_POST['nickname'] ?? '')),
        'rating'   => (int) ($_POST['rating'] ?? 0),
        'body'     => trim((string) ($_POST['body'] ?? '')),
    ];
    if ($form['nickname'] === '' || mb_strlen($form['nickname']) > 30) {
        $errors[] = 'ニックネームは30文字以内で入力してください。';
    }
    if ($form['rating'] < 1 || $form['rating'] > 5) {
        $errors[] = '評価を選択してください。';
    }
    if ($form['body'] === '' || mb_strlen($form['body']) > 1000) {
        $errors[] = 'レビュー本文は1000文字以内で入力してください。';
    }
    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO reviews (product_id, user_id, nickname, rating, body) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$product['id'], $user['id'], $form['nickname'], $form['rating'], $form['body']]);
        flash('success', 'レビューを投稿しました。ありがとうございます！');
        redirect('/product.php?id=' . (int) $product['id'] . '#reviews');
    }
}

$stmt = db()->prepare('SELECT nickname, rating, body, created_at FROM reviews WHERE product_id = ? ORDER BY created_at DESC');
$stmt->execute([$product['id']]);
$reviews = $stmt->fetchAll();
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$stock = (int) $product['stock'];

render('header', ['title' => $product['name']]);
?>
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="/">ホーム</a></li>
      <li class="breadcrumb-item"><a href="/index.php?q=<?= urlencode($product['category']) ?>"><?= h($product['category']) ?></a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= h($product['name']) ?></li>
    </ol>
  </nav>

  <div class="row g-4 g-lg-5">
    <div class="col-md-6">
      <div class="product-gallery">
        <img src="<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>">
      </div>
    </div>
    <div class="col-md-6">
      <div class="mb-2">
        <?php if ($product['tag'] === 'new'): ?><span class="chip chip-new">新商品</span><?php endif; ?>
        <?php if ($product['tag'] === 'sale'): ?><span class="chip chip-sale">セール</span><?php endif; ?>
        <span class="small text-muted ms-1"><?= h($product['category']) ?> ／ 品番 <?= h($product['sku']) ?></span>
      </div>
      <h1 class="h3 fw-bold mb-2"><?= h($product['name']) ?></h1>
      <?php if ($reviews): ?>
      <a href="#reviews" class="small d-inline-block mb-3"><?= stars((int) round($avg)) ?> <span class="text-muted"><?= number_format($avg, 1) ?>（<?= count($reviews) ?>件のレビュー）</span></a>
      <?php endif; ?>
      <p class="mb-3"><?= h($product['summary']) ?></p>
      <div class="price fs-3 mb-1"><?= yen($product['price']) ?><small>（税込）</small><?php if ($product['list_price']): ?><span class="price-old"><?= yen($product['list_price']) ?></span><?php endif; ?></div>
      <div class="small mb-4">
        <?php if ($stock === 0): ?><span class="stock-none">在庫切れ（入荷待ち）</span>
        <?php elseif ($stock <= 10): ?><span class="stock-low">残りわずか（在庫 <?= $stock ?> 点）</span>
        <?php else: ?><span class="stock-ok">在庫あり</span><?php endif; ?>
      </div>

      <form method="post" action="/cart.php" class="panel p-3 mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <label class="small fw-semibold" for="qty">数量</label>
          <div class="input-group qty-selector">
            <button class="btn btn-outline-secondary" type="button" data-qty-step="-1">−</button>
            <input type="number" class="form-control" id="qty" name="qty" value="1" min="1" max="<?= max(1, min(20, $stock)) ?>">
            <button class="btn btn-outline-secondary" type="button" data-qty-step="1">＋</button>
          </div>
          <button type="submit" class="btn btn-primary flex-grow-1 py-2" <?= $stock === 0 ? 'disabled' : '' ?>>カートに入れる</button>
        </div>
      </form>

      <h2 class="h6 fw-bold">商品説明</h2>
      <p class="small lh-lg"><?= nl2br(h($product['description'])) ?></p>
    </div>
  </div>

  <section id="reviews" class="mt-5">
    <h2 class="section-title mb-4">カスタマーレビュー</h2>
    <div class="row g-4">
      <div class="col-lg-7">
        <?php if (!$reviews): ?>
        <p class="text-muted">まだレビューはありません。</p>
        <?php endif; ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($reviews as $review): ?>
          <div class="review">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="review-name"><?= h($review['nickname']) ?></span>
              <span class="review-date"><?= h(format_date($review['created_at'])) ?></span>
            </div>
            <div class="mb-2"><?= stars((int) $review['rating']) ?></div>
            <p class="mb-0 small lh-lg"><?= nl2br($review['body']) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="panel">
          <h3 class="h6 fw-bold mb-3">レビューを書く</h3>
          <?php if (!$user): ?>
          <p class="small text-muted mb-3">レビューの投稿にはログインが必要です。</p>
          <a href="/login.php" class="btn btn-outline-primary w-100">ログインしてレビューを書く</a>
          <?php else: ?>
          <?php foreach ($errors as $e): ?><div class="alert alert-danger small py-2"><?= h($e) ?></div><?php endforeach; ?>
          <form method="post" action="/product.php?id=<?= (int) $product['id'] ?>#reviews">
            <?= csrf_field() ?>
            <div class="mb-2">
              <span class="form-label small fw-semibold d-block">評価</span>
              <div class="rating-input">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="rating<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) $form['rating'] === $i ? 'checked' : '' ?>><label for="rating<?= $i ?>" title="<?= $i ?>">★</label>
                <?php endfor; ?>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold" for="nickname">ニックネーム</label>
              <input class="form-control" id="nickname" name="nickname" maxlength="30" value="<?= h($form['nickname']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold" for="body">レビュー</label>
              <textarea class="form-control" id="body" name="body" rows="4" maxlength="1000" placeholder="使ってみた感想を教えてください" required><?= h($form['body']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-success w-100">投稿する</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</div>
<?php render('footer'); ?>
