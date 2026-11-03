<?php

require dirname(__DIR__) . '/lib/bootstrap.php';

$user = require_login();
$errors = [];

if (is_post()) {
    csrf_verify();
    $section = $_POST['section'] ?? '';

    if ($section === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirmation'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'お名前は100文字以内で入力してください。';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません。';
        }
        $changingCredentials = $email !== $user['email'] || $new !== '';
        if ($changingCredentials) {
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            if (!password_verify($current, (string) $stmt->fetchColumn())) {
                $errors[] = 'メールアドレスまたはパスワードを変更するには、現在のパスワードを入力してください。';
            }
        }
        if ($new !== '' && (strlen($new) < 8 || $new !== $confirm)) {
            $errors[] = '新しいパスワードは8文字以上で、確認用と一致させてください。';
        }
        if (!$errors && $email !== $user['email']) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $user['id']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'このメールアドレスは既に使用されています。';
            }
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $user['id']]);
            if ($new !== '') {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$user['id']]);
                session_regenerate_id(true);
            }
            flash('success', 'アカウント情報を更新しました。');
            redirect('/account.php');
        }
    } elseif ($section === 'avatar') {
        $file = $_FILES['avatar'] ?? null;
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        if (!$file || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '画像ファイルを選択してください。';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = '画像サイズは2MB以内にしてください。';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime || @getimagesize($file['tmp_name']) === false) {
                $errors[] = 'JPEG・PNG・GIF・WebP形式の画像をアップロードしてください。';
            } else {
                $name = 'u' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                $dest = APP_ROOT . '/public/uploads/avatars/' . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if ($user['avatar']) {
                        @unlink(APP_ROOT . '/public/uploads/avatars/' . basename($user['avatar']));
                    }
                    db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$name, $user['id']]);
                    flash('success', 'プロフィール画像を変更しました。');
                    redirect('/account.php');
                }
                $errors[] = '画像の保存に失敗しました。';
            }
        }
    }
}

render('header', ['title' => 'アカウント設定']);
?>
<div class="container py-4">
  <h1 class="section-title mb-4">アカウント設定</h1>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger small py-2"><?= h($e) ?></div><?php endforeach; ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="panel text-center">
        <img src="<?= h(avatar_url($user)) ?>" id="avatar-preview" class="avatar-lg mb-3" alt="">
        <h2 class="h6 fw-bold mb-3">プロフィール画像</h2>
        <form method="post" action="/account.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="section" value="avatar">
          <input class="form-control form-control-sm mb-3" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#avatar-preview" required>
          <button class="btn btn-outline-primary btn-sm w-100" type="submit">画像をアップロード</button>
          <p class="small text-muted mt-2 mb-0">JPEG / PNG / GIF / WebP、2MBまで</p>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="panel">
        <h2 class="h6 fw-bold mb-3">基本情報</h2>
        <form method="post" action="/account.php">
          <?= csrf_field() ?>
          <input type="hidden" name="section" value="profile">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="name">お名前</label>
              <input class="form-control" id="name" name="name" value="<?= h($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold" for="email">メールアドレス</label>
              <input class="form-control" type="email" id="email" name="email" value="<?= h($user['email']) ?>" required>
            </div>
          </div>
          <hr class="my-4">
          <h3 class="h6 fw-bold mb-3">パスワード変更 <small class="text-muted fw-normal">（変更する場合のみ入力）</small></h3>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="current_password">現在のパスワード</label>
              <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="new_password">新しいパスワード</label>
              <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold" for="new_password_confirmation">新しいパスワード（確認）</label>
              <input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password">
            </div>
          </div>
          <button class="btn btn-primary mt-4 px-4" type="submit">変更を保存</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php render('footer'); ?>
