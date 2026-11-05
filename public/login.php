<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

if (current_user()) {
    redirect('/');
}

$error = null;
$email = '';

if (is_post()) {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $sql = "SELECT id, name, email, password_hash, role FROM users WHERE email = '" . $email . "' LIMIT 1";
    $user = db()->query($sql)->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user, !empty($_POST['remember']));
        flash('success', 'ログインしました。' . $user['name'] . ' さん、ようこそ！');
        redirect($user['role'] === 'admin' ? '/admin/index.php' : '/');
    }
    $error = 'メールアドレスまたはパスワードが正しくありません。';
}

render('header', ['title' => 'ログイン']);
?>
<div class="container">
  <div class="auth-card panel p-4 p-md-5">
    <div class="text-center mb-4">
      <img src="/images/logo.svg" width="64" height="64" alt="">
      <h1 class="h4 fw-bold mt-3 mb-1">ログイン</h1>
      <p class="text-muted small mb-0">うさぎ商事オンラインショップへようこそ</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger small"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="/login.php" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="email">メールアドレス</label>
        <input class="form-control" type="email" id="email" name="email" value="<?= h($email) ?>" autocomplete="username" required>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="password">パスワード</label>
        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
        <label class="form-check-label small" for="remember">ログイン状態を保持</label>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">ログイン</button>
    </form>
  </div>
</div>
<?php render('footer'); ?>
