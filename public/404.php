<?php

if (!defined('APP_ROOT')) {
    require dirname(__DIR__) . '/lib/bootstrap.php';
    http_response_code(404);
}
render('header', ['title' => 'ページが見つかりません']);
?>
<div class="container py-5 text-center">
  <img src="/images/avatar-default.svg" width="120" height="120" class="rounded-circle mb-4" alt="">
  <h1 class="h3 fw-bold mb-3">ページが見つかりませんでした</h1>
  <p class="text-muted mb-4">お探しのページは移動または削除された可能性があります。</p>
  <a href="/" class="btn btn-primary px-4">トップページへ</a>
</div>
<?php render('footer'); ?>
