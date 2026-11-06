<?php

require dirname(__DIR__, 2) . '/lib/bootstrap.php';

$admin = require_admin();
$errors = [];

if (is_post()) {
    csrf_verify();
    $productId = (int) ($_POST['product_id'] ?? 0);
    $stmt = db()->prepare('SELECT id, name FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    $upload = $_FILES['image'] ?? null;
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    if (!$product) {
        $errors[] = '商品を選択してください。';
    } elseif (!$upload || !is_array($upload) || $upload['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '画像ファイルを選択してください。';
    } else {
        $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            $errors[] = 'JPEG または PNG 形式の画像をアップロードしてください。';
        }
    }

    if (!$errors) {
        $workDir = sys_get_temp_dir() . '/usagi-upload';
        if (!is_dir($workDir)) {
            mkdir($workDir, 0700, true);
        }
        $outExt = $ext === 'png' ? 'png' : 'jpg';
        $source = $workDir . '/' . bin2hex(random_bytes(8)) . '.' . $outExt;
        move_uploaded_file($upload['tmp_name'], $source);

        $filename = 'p' . $product['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $outExt;
        $dest = APP_ROOT . '/public/uploads/products/' . $filename;

        exec('convert ' . escapeshellarg($source) . ' -resize 800x800 ' . escapeshellarg($dest) . ' 2>&1', $output, $status);
        if ($status !== 0 || !is_file($dest)) {
            $img = @imagecreatefromstring((string) file_get_contents($source));
            if ($img !== false) {
                $w = imagesx($img);
                $h = imagesy($img);
                $scale = min(1, 800 / max($w, $h));
                $resized = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
                $ext === 'png' ? imagepng($resized, $dest) : imagejpeg($resized, $dest, 88);
            }
        }
        @unlink($source);

        if (is_file($dest)) {
            db()->prepare('UPDATE products SET image = ? WHERE id = ?')->execute(['/uploads/products/' . $filename, $product['id']]);
            flash('success', '「' . $product['name'] . '」の商品画像を更新しました。');
            redirect('/admin/index.php#product-image');
        }
        $errors[] = '画像の変換に失敗しました。';
    }
}

$today = db()->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS sales FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'")->fetch();
$month = db()->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status <> 'cancelled'")->fetchColumn();
$soldOut = db()->query('SELECT id, name FROM products WHERE stock = 0 ORDER BY id')->fetchAll();
$processing = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$orders = db()->query('SELECT o.id, o.order_no, o.created_at, o.total, o.status, o.payment_method, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 20')->fetchAll();
$products = db()->query('SELECT id, name, image, stock FROM products ORDER BY id')->fetchAll();

render('admin_header', ['title' => 'ダッシュボード', 'active' => 'dashboard']);
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger small py-2"><?= h($e) ?></div><?php endforeach; ?>
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">本日の注文数</div><div class="value"><?= (int) $today['cnt'] ?><small class="fs-6 fw-normal ms-1">件</small></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card green"><div class="label">本日の売上</div><div class="value"><?= yen($today['sales']) ?></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card pink"><div class="label">在庫切れ</div><div class="value"><?= count($soldOut) ?><small class="fs-6 fw-normal ms-1">商品</small></div><div class="small text-muted text-truncate"><?= h(implode('、', array_column($soldOut, 'name'))) ?: 'なし' ?></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">今月の売上 ／ 未発送</div><div class="value fs-4"><?= yen($month) ?></div><div class="small text-muted">処理中の注文 <?= $processing ?> 件</div></div></div>
</div>

<div class="panel p-0 overflow-hidden mb-4" id="orders">
  <div class="d-flex justify-content-between align-items-center px-4 pt-4 pb-2">
    <h2 class="h6 fw-bold mb-0">最近の注文</h2>
    <a href="/admin/export.php" class="btn btn-success btn-sm">CSVエクスポート</a>
  </div>
  <div class="table-responsive">
    <table class="table table-usagi mb-0">
      <thead><tr><th class="ps-4">注文番号</th><th>注文日時</th><th>顧客</th><th>支払方法</th><th class="text-end">合計</th><th class="text-center pe-4">ステータス</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td class="ps-4 fw-semibold"><?= h($o['order_no']) ?></td>
          <td><?= h(format_date($o['created_at'], 'Y/m/d H:i')) ?></td>
          <td><?= h($o['customer']) ?></td>
          <td><?= h($o['payment_method']) ?></td>
          <td class="text-end"><?= yen($o['total']) ?></td>
          <td class="text-center pe-4"><?= order_status_badge($o['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-4" id="product-image">
  <div class="col-xl-5">
    <div class="panel h-100">
      <h2 class="h6 fw-bold mb-3">商品画像のアップロード</h2>
      <form method="post" action="/admin/index.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="product_id">商品</label>
          <select class="form-select" id="product_id" name="product_id" required>
            <?php foreach ($products as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex gap-3 align-items-center mb-3">
          <img src="/images/avatar-default.svg" id="product-preview" class="upload-preview" alt="">
          <div class="flex-grow-1">
            <input class="form-control form-control-sm" type="file" id="image" name="image" accept="image/jpeg,image/png" data-preview="#product-preview" required>
            <p class="small text-muted mt-2 mb-0">JPEG / PNG。長辺800pxに自動でリサイズされます。</p>
          </div>
        </div>
        <button class="btn btn-primary w-100" type="submit">アップロード</button>
      </form>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="panel h-100">
      <h2 class="h6 fw-bold mb-3">商品一覧</h2>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php foreach ($products as $p): ?>
        <div class="col text-center small">
          <img src="<?= h($p['image']) ?>" class="upload-preview mb-1" alt="">
          <div class="text-truncate"><?= h($p['name']) ?></div>
          <div class="<?= (int) $p['stock'] === 0 ? 'stock-none' : 'text-muted' ?>">在庫 <?= (int) $p['stock'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php render('admin_footer'); ?>
