# 脅威モデル: うさぎ商事オンラインショップ（Devin-Security-Demo/usagi-shop）

## 1. システム概要
- うさぎ用品の小規模 EC サイト。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4。注文確認メールは PHPMailer 経由の SMTP（開発時は Mailhog）。
- デプロイ構成はリポジトリ内の `Dockerfile`、`docker-compose.yml`（web / composer / db / mailhog）、`apache/000-default.conf`、`php/99-app.ini` のみ。別のインフラリポジトリ（Terraform / Kubernetes 等）は確認できず、ユーザーも不明と回答。
- 公開範囲: **ユーザー回答は「不明」**。インターネット公開の可能性ありとして扱う（優先度を動かす根拠にはしない）。
- 決済は代金引換・銀行振込のみ（`public/checkout.php` の `$payments`）。カード情報は扱わない。

## 2. 信頼境界
1. インターネット → Apache（`DocumentRoot /var/www/html/public`、`public/` 外は `Require all denied`）
2. 匿名 → ログイン済み会員（`require_login()`、`lib/auth.php`）
3. 会員 → 管理者（`require_admin()`、`users.role = 'admin'`）
4. Web アプリ → 内部サービス（MySQL `db:3306`、SMTP `mailhog:1025`、自身の配送 API `http://127.0.0.1/api/shipping.php` を `ShippingClient` で呼び出し）
5. アップロードファイル → サーバー（`public/uploads/` は `php_admin_flag engine off`、管理画面の商品画像は `exec("convert ...")` で処理）

## 3. コンポーネント
| 名前 | 対象コード |
| --- | --- |
| ストアフロント | `public/index.php`, `product.php`, `cart.php`, `checkout.php`, `orders.php`, `order.php`, `invoice.php`, `account.php`, `about.php`, `404.php`, `lib/Cart.php`, `lib/helpers.php`, `lib/views/*`（admin_* 以外）, `public/js/*`, `public/css/*` |
| 認証・セッション | `lib/auth.php`, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| 管理画面 | `public/admin/index.php`, `public/admin/export.php`, `lib/views/admin_*` |
| 配送API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| メール送信 | `lib/OrderMailer.php` |
| データベース | `lib/db.php`, `config/database.php`, `docker/mysql/init/*.sql` |
| インフラ・依存関係 | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/`, `.env`, `config/app.php`, `composer.json`/`composer.lock`, `.github/workflows/`, `storage/` |

## 4. エントリポイント
- 匿名: `/`（検索 `q`）、`/product.php?id=`、`/cart.php`（POST）、`/login.php`（POST）、`/about.php`、`/api/shipping.php?zip=&subtotal=`（認証なし）、`remember` Cookie（`auth_restore_from_cookie()` で全リクエストに処理）
- 会員: `/checkout.php`（注文作成・`?done=`）、`/orders.php`、`/order.php?id=`、`/invoice.php?file=`、`/account.php`（プロフィール・パスワード・アバターアップロード）、`/product.php`（レビュー POST）、`/logout.php`
- 管理者: `/admin/`（ダッシュボード・商品画像アップロード）、`/admin/export.php`（全注文 CSV）

## 5. 資産と重要度（ユーザー回答: 以下 3 つすべてが最重要）
- **最重要**: 会員の個人情報・注文情報（氏名・メール・住所・電話番号、注文履歴、請求書 PDF `storage/invoices/`、CSV エクスポート）
- **最重要**: 管理者アカウント・セッション、サーバー（RCE）、DB 認証情報（`config/database.php`）
- **最重要**: 価格・在庫・送料の正しさ（`Cart`, `checkout.php`, `ShippingCalculator`, `ShippingClient`）
- 中（コードからの推定）: 商品・レビュー表示の完全性

## 6. 攻撃者（ユーザー回答: すべて対象）
- 匿名のインターネット利用者
- ログイン済みの一般会員（他会員のデータや管理機能への到達を狙う）
- 悪意のある管理者（管理画面からのサーバー侵害、権限の濫用）
- → 特定の攻撃者を理由に優先度を下げることはしない。

## 7. 既存の対策（コードから確認）
- SQL: PDO プリペアドステートメント、`ATTR_EMULATE_PREPARES=false`（ただし `public/login.php` は文字列連結のクエリ）
- 出力: `h()`（`htmlspecialchars` ENT_QUOTES）
- CSRF: セッショントークン + `hash_equals`（`csrf_verify()`）、Cookie `SameSite=Lax`
- セッション: `use_strict_mode`, `cookie_httponly`, ログイン時 `session_regenerate_id(true)`
- remember-me: ランダム 32 バイト、DB には SHA-256 ハッシュ保存（Cookie は `serialize()` 形式で `unserialize()` される）
- パスワード: `password_hash` / `PASSWORD_DEFAULT`
- ヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- アップロード: 拡張子 + finfo MIME チェック、ランダムファイル名、`public/uploads` で PHP 無効化
- CSV エクスポート: 数式インジェクション対策（先頭 `= + - @` にクォート）
- Apache: `Options -Indexes`、`public/` 外へのアクセス拒否

## 8. 対象外・許容リスク（ユーザー回答）
- **サードパーティ依存の既知脆弱性**（例: `phpmailer/phpmailer` 5.2.16、`guzzlehttp/guzzle` 6.5.5、`public/js/jquery-1.12.4.min.js`、ベースイメージのパッケージ）。依存のバージョンが古いこと自体の指摘は許容リスク。アプリ自身のコードがその依存を危険な使い方で呼んでいる問題は対象。
- **CI（GitHub Actions、`.github/workflows/`）**
- **開発用構成**: Mailhog（認証なし SMTP・Web UI 公開）、`display_errors = On`、テスト用アカウント（`password`、ログイン画面の表示を含む）、`.env` の認証情報。
- 上記以外（`config/database.php` へのハードコード等アプリ側コード）は対象。

## 9. 未解決の質問
- 本番の公開範囲・TLS 終端・リバースプロキシ・WAF の有無（ユーザー回答「不明」）。
- 本番でも Docker Compose 構成をそのまま使うか（使う場合、開発用構成の許容は本番にも及ぶのか）。
