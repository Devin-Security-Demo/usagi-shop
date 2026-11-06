<?php
/** @var string $title */
$user = current_user();
$cartCount = cart()->count();
$active = $active ?? '';
$q = $_GET['q'] ?? '';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | うさぎ商事オンラインショップ</title>
<link rel="icon" href="/images/logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/app.css">
</head>
<body>
<header class="site-header sticky-top">
  <div class="topbar text-center small">5,000円以上のご注文で送料無料（沖縄・離島を除く）</div>
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="/">
        <img src="/images/logo.svg" alt="" width="44" height="44">
        <span class="brand-text">うさぎ商事<small>オンラインショップ</small></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="メニュー">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <form class="search-form mx-lg-4 my-3 my-lg-0 flex-grow-1" action="/index.php" method="get" role="search">
          <div class="input-group">
            <input class="form-control" type="search" name="q" value="<?= h(is_string($q) ? $q : '') ?>" placeholder="牧草・おやつ・グッズを検索" aria-label="商品検索">
            <button class="btn btn-search" type="submit">検索</button>
          </div>
          <div class="form-check form-check-inline small mt-1 ms-3 mb-0 instock-check">
            <input class="form-check-input" type="checkbox" id="instock-header" name="in_stock" value="1"<?= ($_GET['in_stock'] ?? '') === '1' ? ' checked' : '' ?>>
            <label class="form-check-label" for="instock-header">在庫ありのみ</label>
          </div>
        </form>
        <ul class="navbar-nav align-items-lg-center gap-lg-2">
          <li class="nav-item">
            <a class="nav-link cart-link<?= $active === 'cart' ? ' active' : '' ?>" href="/cart.php">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
              カート<span class="badge rounded-pill cart-badge" id="cart-count"><?= (int) $cartCount ?></span>
            </a>
          </li>
          <?php if ($user): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= h(avatar_url($user)) ?>" class="avatar-sm" alt="">
              <span><?= h($user['name']) ?> さん</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/orders.php">マイページ（注文履歴）</a></li>
              <li><a class="dropdown-item" href="/account.php">アカウント設定</a></li>
              <?php if ($user['role'] === 'admin'): ?>
              <li><a class="dropdown-item" href="/admin/index.php">管理画面</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form action="/logout.php" method="post" class="px-3">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-link p-0 text-danger text-decoration-none">ログアウト</button>
                </form>
              </li>
            </ul>
          </li>
          <?php else: ?>
          <li class="nav-item"><a class="btn btn-primary btn-sm px-3" href="/login.php">ログイン</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>
<main class="site-main">
<?php foreach (take_flashes() as $flash): ?>
  <div class="container mt-3">
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show auto-dismiss" role="alert">
      <?= h($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
    </div>
  </div>
<?php endforeach; ?>
