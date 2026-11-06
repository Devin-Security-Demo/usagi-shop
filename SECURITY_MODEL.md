# 脅威モデル: うさぎ商事オンラインショップ（Devin-Security-Demo/usagi-shop）

## 1. システム概要
- うさぎ用品の小規模 EC サイト。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4。注文確認メールは PHPMailer で SMTP 送信（開発時は Mailhog）。
- デプロイ構成はリポジトリ内の `Dockerfile`、`docker-compose.yml`（web / composer / db / mailhog）、`apache/000-default.conf`、`php/99-app.ini` のみ。**別のインフラリポジトリはない（ユーザー回答）**。
- **インターネットに公開された本番環境として扱う（ユーザー回答）**。
- 決済は代金引換・銀行振込のみで、カード情報は扱わない（コードからの推測）。

## 2. 信頼境界
1. インターネット → Apache（`DocumentRoot /var/www/html/public`。`public/` 外は `Require all denied`）
2. 匿名 → ログイン済み会員（`require_login()`、`lib/auth.php`）
3. 会員 → 管理者（`require_admin()`、`users.role = 'admin'`）
4. Web アプリ → 内部サービス（MySQL `db:3306`、SMTP `mailhog:1025`、自身の配送 API `http://127.0.0.1/api/shipping.php` を `lib/ShippingClient.php` が呼び出す）
5. アップロードファイル → サーバー（`public/uploads/` は `php_admin_flag engine off`。管理画面の商品画像は `sys_get_temp_dir()/usagi-upload` 経由で ImageMagick `convert` を `exec()`）

## 3. コンポーネント
| 名前 | 対象コード |
| --- | --- |
| ストアフロント | `public/index.php`, `product.php`, `cart.php`, `checkout.php`, `orders.php`, `order.php`, `invoice.php`, `account.php`, `about.php`, `404.php`, `lib/Cart.php`, `lib/helpers.php`, `lib/views/header.php`, `lib/views/footer.php`, `public/js/*`, `storage/invoices/` |
| 認証・セッション | `lib/auth.php`, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| 管理画面 | `public/admin/index.php`, `public/admin/export.php`, `lib/views/admin_*` |
| 配送API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| メール送信 | `lib/OrderMailer.php` |
| データベース | `lib/db.php`, `config/database.php`, `docker/mysql/init/*.sql` |
| インフラ・依存関係 | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/`, `.env`, `config/app.php`, `composer.json`/`composer.lock`, `.github/workflows/` |

## 4. エントリポイント
- 匿名: `/`（検索 `q`）、`/product.php?id=`、`/cart.php`（POST）、`/login.php`、`/about.php`、`/api/shipping.php?zip=&subtotal=`（認証なし）、`remember` Cookie（`auth_restore_from_cookie()` が全リクエストで `unserialize()`）
- 会員: `/checkout.php`（注文作成、`?done=`）、`/orders.php`、`/order.php?id=`、`/invoice.php?file=`、`/account.php`（プロフィール・パスワード・アバターアップロード）、`/product.php`（レビュー POST）、`/logout.php`
- 管理者: `/admin/`（ダッシュボード、商品画像アップロード）、`/admin/export.php`（全注文 CSV）
- 公開ポート: web `8080`、Mailhog UI `8025`（`docker-compose.yml`）

## 5. 資産と重要度
- **最重要（ユーザー回答）**: 会員の個人情報・注文情報（氏名・メール・住所・電話番号、注文履歴、請求書 PDF `storage/invoices/`、CSV エクスポート、Mailhog に溜まる注文メール）
- **最重要（ユーザー回答）**: DB などの認証情報（`config/database.php`、`.env` の MySQL ユーザー・root パスワード）
- 高: 管理者アカウント・セッション、サーバー（RCE）— ユーザーは最重要に選ばなかったが、上記の最重要資産に直結する
- 中: 価格・在庫・送料の正しさ、商品・レビュー表示の完全性

## 6. 攻撃者（ユーザー回答: すべて）
- 匿名のインターネット利用者
- ログイン済みの一般会員（他会員のデータや管理機能を狙う）
- 悪意のある管理者（管理者は信頼しない。管理画面からのサーバー侵害や権限の濫用も対象）
- → 攻撃者を理由に優先度を下げることはしない。管理者のみが到達できる経路も対象のまま。

## 7. 既存の対策（コードから確認）
- SQL: PDO プリペアドステートメント、`ATTR_EMULATE_PREPARES=false`（`lib/db.php`）
- 出力: `h()`（`htmlspecialchars` ENT_QUOTES | ENT_SUBSTITUTE）
- CSRF: セッショントークン + `hash_equals`（`csrf_verify()`）、セッション Cookie `SameSite=Lax`
- セッション: `use_strict_mode`, `cookie_httponly`、ログイン時 `session_regenerate_id(true)`。`cookie_secure` は未設定
- remember-me: ランダム 32 バイト、DB には SHA-256 ハッシュ保存（ただし Cookie は PHP `serialize()` 形式）
- パスワード: `password_hash` / `PASSWORD_DEFAULT`
- ヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- アップロード: 拡張子 + finfo MIME チェック、ランダムファイル名、`public/uploads` で PHP 無効化
- CSV エクスポート: 先頭文字のエスケープ（数式インジェクション対策）
- Apache: `Options -Indexes`、`public/` 外へのアクセス拒否
- 弱点として目立つ設定: `display_errors = On`、`expose_php = On`、認証情報のハードコード（`config/database.php`、`.env` がコミット済み）

## 8. 対象外・許容リスク（ユーザー回答: なし）
- 開発用構成（Mailhog、`display_errors`、テスト用アカウント、`.env`）、CI、サードパーティ依存の既知脆弱性もすべて対象。

## 9. 優先度付けの方針
- スキャナーの深刻度を出発点とし、上記のユーザー回答に基づく事実でのみ動かす。
- 対象外・許容リスクも攻撃者の除外もないため、下げる理由は基本的にない。
- 上げるのは、スキャナーが確認した経路で最重要資産（会員の個人情報・注文情報、DB 認証情報）に到達し、悪用が容易な場合に限る。

## 10. 未解決の質問
- 本番の TLS 終端・リバースプロキシ・WAF の有無（リポジトリ外）。
- 本番で Mailhog（`8025`）や `display_errors` が実際に有効か。
