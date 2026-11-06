<?php
$user = current_user();
$active = $active ?? 'dashboard';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | うさぎ商事 管理画面</title>
<link rel="icon" href="/images/logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="/css/bootstrap.min.css">
<link rel="stylesheet" href="/css/app.css">
</head>
<body class="admin-body">
<div class="admin-layout">
  <aside class="admin-sidebar">
    <a class="d-flex align-items-center gap-2 text-decoration-none mb-4 px-2" href="/admin/index.php">
      <img src="/images/logo.svg" alt="" width="36" height="36">
      <span class="brand-text text-white">うさぎ商事<small>管理画面</small></span>
    </a>
    <nav class="nav flex-column gap-1">
      <a class="nav-link<?= $active === 'dashboard' ? ' active' : '' ?>" href="/admin/index.php">ダッシュボード</a>
      <a class="nav-link" href="/admin/index.php#orders">注文一覧</a>
      <a class="nav-link<?= $active === 'product_new' ? ' active' : '' ?>" href="/admin/products.php">商品追加</a>
      <a class="nav-link" href="/admin/index.php#product-image">商品画像</a>
      <a class="nav-link" href="/admin/export.php">CSVエクスポート</a>
      <hr class="border-light opacity-25">
      <a class="nav-link" href="/">ショップを表示</a>
    </nav>
    <div class="admin-user small mt-auto px-2">
      <img src="<?= h(avatar_url($user)) ?>" class="avatar-sm me-2" alt=""><?= h($user['name']) ?>
    </div>
  </aside>
  <div class="admin-content">
    <header class="admin-topbar d-flex justify-content-between align-items-center">
      <h1 class="h5 mb-0"><?= h($title) ?></h1>
      <form action="/logout.php" method="post"><?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm">ログアウト</button></form>
    </header>
    <div class="p-4">
<?php foreach (take_flashes() as $flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
      </div>
<?php endforeach; ?>
