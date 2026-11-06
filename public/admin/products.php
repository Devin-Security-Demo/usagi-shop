<?php

require dirname(__DIR__, 2) . '/lib/bootstrap.php';

use Usagi\ProductForm;

const PRODUCT_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const PRODUCT_IMAGE_MAX_SIDE = 800;
const PRODUCT_IMAGE_DEFAULT = '/images/logo.svg';

/** @return string|null error message */
function validate_product_image(array $upload): ?string
{
    if ($upload['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        return '商品画像は5MB以内にしてください。';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true) || @getimagesize($upload['tmp_name']) === false) {
        return '商品画像は JPEG または PNG 形式のファイルを選択してください。';
    }
    return null;
}

/** Re-encodes the upload with GD (no shell commands) and returns its public path. */
function store_product_image(array $upload): ?string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $img = @imagecreatefromstring((string) file_get_contents($upload['tmp_name']));
    if ($img === false) {
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, PRODUCT_IMAGE_MAX_SIDE / max($w, $h));
    if ($scale < 1) {
        $img = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
    }
    $png = $mime === 'image/png';
    $filename = 'new_' . bin2hex(random_bytes(8)) . ($png ? '.png' : '.jpg');
    $dest = APP_ROOT . '/public/uploads/products/' . $filename;
    if ($png) {
        imagesavealpha($img, true);
    }
    $saved = $png ? imagepng($img, $dest) : imagejpeg($img, $dest, 88);
    return $saved ? '/uploads/products/' . $filename : null;
}

$admin = require_admin();
$form = new ProductForm();
$errors = [];

if (is_post()) {
    csrf_verify();
    $form = new ProductForm($_POST);
    $errors = $form->validate();
    $values = $form->values();

    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE sku = ?');
        $stmt->execute([$values['sku']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = '商品コード「' . $values['sku'] . '」は既に登録されています。';
        }
    }

    $upload = $_FILES['image'] ?? null;
    $hasUpload = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasUpload) {
        $imageError = $upload['error'] !== UPLOAD_ERR_OK ? '商品画像のアップロードに失敗しました。' : validate_product_image($upload);
        if ($imageError !== null) {
            $errors[] = $imageError;
        }
    }

    if (!$errors) {
        $image = $hasUpload ? store_product_image($upload) : PRODUCT_IMAGE_DEFAULT;
        if ($image === null) {
            $errors[] = '商品画像の変換に失敗しました。';
        } else {
            $stmt = db()->prepare('INSERT INTO products (sku, name, category, summary, description, price, list_price, stock, tag, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $values['sku'], $values['name'], $values['category'], $values['summary'], $values['description'],
                $values['price'], $values['list_price'], $values['stock'], $values['tag'], $image,
            ]);
            flash('success', '「' . $values['name'] . '」を追加しました（商品ID: ' . db()->lastInsertId() . '）。');
            redirect('/admin/products.php');
        }
    }
}

$in = $form->input();
$categories = db()->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
$recent = db()->query('SELECT id, sku, name, category, price, stock, image FROM products ORDER BY id DESC LIMIT 10')->fetchAll();

render('admin_header', ['title' => '商品追加', 'active' => 'product_new']);
?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger small py-2"><?= h($e) ?></div><?php endforeach; ?>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="panel">
      <h2 class="h6 fw-bold mb-3">新しい商品を追加</h2>
      <form method="post" action="/admin/products.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-semibold" for="sku">商品コード（SKU）</label>
            <input class="form-control" id="sku" name="sku" value="<?= h($in['sku']) ?>" maxlength="20" placeholder="US-SN-003" required>
          </div>
          <div class="col-md-8">
            <label class="form-label small fw-semibold" for="name">商品名</label>
            <input class="form-control" id="name" name="name" value="<?= h($in['name']) ?>" maxlength="150" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold" for="category">カテゴリ</label>
            <input class="form-control" id="category" name="category" value="<?= h($in['category']) ?>" maxlength="50" list="category-list" required>
            <datalist id="category-list">
              <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-8">
            <label class="form-label small fw-semibold" for="summary">概要（一覧に表示）</label>
            <input class="form-control" id="summary" name="summary" value="<?= h($in['summary']) ?>" maxlength="255" required>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold" for="description">商品説明</label>
            <textarea class="form-control" id="description" name="description" rows="6" maxlength="5000" required><?= h($in['description']) ?></textarea>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold" for="price">販売価格（税込・円）</label>
            <input class="form-control" id="price" name="price" value="<?= h($in['price']) ?>" inputmode="numeric" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold" for="list_price">通常価格（任意）</label>
            <input class="form-control" id="list_price" name="list_price" value="<?= h($in['list_price']) ?>" inputmode="numeric">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold" for="stock">在庫数</label>
            <input class="form-control" id="stock" name="stock" value="<?= h($in['stock']) ?>" inputmode="numeric" required>
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold" for="tag">タグ</label>
            <select class="form-select" id="tag" name="tag">
              <?php foreach (ProductForm::TAGS as $value => $label): ?>
              <option value="<?= h($value) ?>"<?= $in['tag'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold" for="image">商品画像（任意）</label>
            <div class="d-flex gap-3 align-items-center">
              <img src="<?= h(PRODUCT_IMAGE_DEFAULT) ?>" id="new-product-preview" class="upload-preview" alt="">
              <div class="flex-grow-1">
                <input class="form-control form-control-sm" type="file" id="image" name="image" accept="image/jpeg,image/png" data-preview="#new-product-preview">
                <p class="small text-muted mt-2 mb-0">JPEG / PNG（5MBまで）。長辺800pxに自動でリサイズされます。未選択の場合はロゴ画像が表示されます。</p>
              </div>
            </div>
          </div>
        </div>
        <button class="btn btn-primary w-100 mt-4" type="submit">商品を追加</button>
      </form>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="panel p-0 overflow-hidden">
      <h2 class="h6 fw-bold mb-0 px-4 pt-4 pb-2">最近追加した商品</h2>
      <div class="table-responsive">
        <table class="table table-usagi mb-0">
          <thead><tr><th class="ps-4">商品</th><th class="text-end">価格</th><th class="text-end pe-4">在庫</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $p): ?>
            <tr>
              <td class="ps-4">
                <a href="/product.php?id=<?= (int) $p['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none" target="_blank" rel="noopener">
                  <img src="<?= h($p['image']) ?>" width="36" height="36" class="rounded" alt="">
                  <span><span class="d-block fw-semibold text-truncate"><?= h($p['name']) ?></span><small class="text-muted"><?= h($p['sku']) ?> ／ <?= h($p['category']) ?></small></span>
                </a>
              </td>
              <td class="text-end"><?= yen($p['price']) ?></td>
              <td class="text-end pe-4 <?= (int) $p['stock'] === 0 ? 'stock-none' : '' ?>"><?= (int) $p['stock'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php render('admin_footer'); ?>
