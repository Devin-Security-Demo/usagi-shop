<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $stmt = db()->prepare('SELECT * FROM products WHERE name LIKE ? OR summary LIKE ? OR category LIKE ? ORDER BY id');
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = db()->query('SELECT * FROM products ORDER BY id');
}
$products = $stmt->fetchAll();

$ratings = [];
foreach (db()->query('SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM reviews GROUP BY product_id') as $row) {
    $ratings[$row['product_id']] = $row;
}

$featured = db()->query('SELECT * FROM products WHERE featured = 1 ORDER BY id LIMIT 1')->fetch();

render('header', ['title' => $q !== '' ? '「' . $q . '」の検索結果' : 'トップ']);
?>
<div class="container py-4">
  <?php if ($q === '' && $featured): ?>
  <section class="hero p-4 p-md-5 mb-5">
    <div class="row align-items-center g-4">
      <div class="col-md-6">
        <span class="hero-label mb-3">季節のおすすめ</span>
        <h1 class="mb-3">秋の実りを、<br>うさぎさんにも。</h1>
        <p class="mb-4">北海道・十勝の契約農家から届いた新物にんじんを、低温でじっくり乾燥。<br class="d-none d-lg-inline">カリッと甘い、ごほうびおやつが新登場です。</p>
        <a href="/product.php?id=<?= (int) $featured['id'] ?>" class="btn btn-primary btn-lg px-4">商品を見る</a>
        <span class="ms-3 price"><?= yen($featured['price']) ?><small>（税込）</small></span>
      </div>
      <div class="col-md-6 text-center">
        <img src="/images/hero.svg" class="img-fluid" alt="うさぎと牧草とにんじん">
      </div>
    </div>
  </section>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-end mb-3">
    <h2 class="section-title mb-0"><?= $q !== '' ? '「' . h($q) . '」の検索結果' : '商品一覧' ?></h2>
    <span class="text-muted small"><?= count($products) ?>件</span>
  </div>

  <?php if (!$products): ?>
  <div class="panel text-center py-5">
    <p class="mb-3">該当する商品が見つかりませんでした。</p>
    <a href="/" class="btn btn-outline-primary">すべての商品を見る</a>
  </div>
  <?php else: ?>
  <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
    <?php foreach ($products as $p): $r = $ratings[$p['id']] ?? null; ?>
    <div class="col">
      <a href="/product.php?id=<?= (int) $p['id'] ?>" class="card product-card">
        <div class="thumb">
          <img src="<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>" loading="lazy">
          <div class="chips">
            <?php if ($p['tag'] === 'new'): ?><span class="chip chip-new">新商品</span><?php endif; ?>
            <?php if ($p['tag'] === 'sale'): ?><span class="chip chip-sale">セール</span><?php endif; ?>
            <?php if ((int) $p['stock'] === 0): ?><span class="chip chip-soldout">在庫切れ</span><?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <div class="small text-muted mb-1"><?= h($p['category']) ?></div>
          <h3 class="card-title mb-2"><?= h($p['name']) ?></h3>
          <?php if ($r): ?>
          <div class="small mb-2"><?= stars((int) round($r['avg_rating'])) ?> <span class="text-muted">(<?= (int) $r['cnt'] ?>)</span></div>
          <?php endif; ?>
          <div class="price"><?= yen($p['price']) ?><small>（税込）</small><?php if ($p['list_price']): ?><span class="price-old"><?= yen($p['list_price']) ?></span><?php endif; ?></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php render('footer'); ?>
