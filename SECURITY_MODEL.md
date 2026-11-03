# 脅威モデル: うさぎ商事オンラインショップ（Devin-Security-Demo/usagi-shop）

## 1. システム概要
- うさぎ用品の小規模 EC サイト。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4。メールは SMTP（開発時は Mailhog）。
- デプロイ構成は同リポジトリ内の `Dockerfile`、`docker-compose.yml`（web / composer / db / mailhog）、`apache/000-default.conf`、`php/99-app.ini`。専用のインフラリポジトリ（Terraform / Kubernetes 等）はなし。
- **ユーザー回答**: インターネットに公開された本番環境として扱う。

## 2. 信頼境界
1. インターネット → Apache（`DocumentRoot /var/www/html/public`、`public/` 外は `Require all denied`）
2. 匿名 → ログイン済み会員（`require_login()`、`lib/auth.php`）
3. 会員 → 管理者（`require_admin()`、`users.role = 'admin'`）
4. Web アプリ → 内部サービス（MySQL `db:3306`、SMTP `mailhog:1025`、自身の配送 API `http://127.0.0.1/api/shipping.php`）
5. アップロードファイル → サーバー（`public/uploads/` は PHP 実行無効）

## 3. コンポーネント
| 名前 | 対象コード |
| --- | --- |
| ストアフロント | `public/index.php`, `product.php`, `cart.php`, `checkout.php`, `orders.php`, `order.php`, `invoice.php`, `account.php`, `about.php`, `404.php`, `lib/Cart.php`, `lib/helpers.php`, `lib/views/*`, `public/js/*` |
| 認証・セッション | `lib/auth.php`, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| 管理画面 | `public/admin/index.php`, `public/admin/export.php`, `lib/views/admin_*` |
| 配送API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| メール送信 | `lib/OrderMailer.php` |
| データベース | `lib/db.php`, `config/database.php`, `docker/mysql/init/*.sql` |
| インフラ・依存関係 | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/`, `.env`, `composer.json`/`composer.lock`, `.github/workflows/` |

## 4. エントリポイント
- 匿名: `/`（検索 `q`）、`/product.php?id=`（閲覧）、`/cart.php`（POST）、`/login.php`、`/about.php`、`/api/shipping.php?zip=&subtotal=`（認証なし）、`remember` Cookie（`auth_restore_from_cookie()` で全リクエストに処理）
- 会員: `/checkout.php`（注文作成・`?done=`）、`/orders.php`、`/order.php?id=`、`/invoice.php?file=`、`/account.php`（プロフィール・パスワード・アバターアップロード）、`/product.php`（レビュー POST）、`/logout.php`
- 管理者: `/admin/`（ダッシュボード・商品画像アップロード）、`/admin/export.php`（全注文 CSV）

## 5. 資産と重要度
- **最重要（ユーザー回答）**: 会員の個人情報・注文情報（氏名・メール・住所・電話番号、注文履歴、請求書 PDF `storage/invoices/`、CSV エクスポート）
- 高: 管理者アカウント・セッション、サーバー（RCE）、DB 認証情報（`config/database.php`, `.env`）
- 中: 価格・在庫・送料の正しさ、商品・レビュー表示の完全性

## 6. 攻撃者（ユーザー回答）
- 匿名のインターネット利用者
- ログイン済みの一般会員（他会員のデータや管理機能への到達を狙う）
- 悪意のある管理者（管理画面からのサーバー侵害、権限の濫用）
- → すべての攻撃者が対象。特定の攻撃者を理由に優先度を下げることはしない。

## 7. 既存の対策（コードから確認）
- SQL: PDO プリペアドステートメント、`ATTR_EMULATE_PREPARES=false`
- 出力: `h()`（`htmlspecialchars` ENT_QUOTES）
- CSRF: セッショントークン + `hash_equals`（`csrf_verify()`）、Cookie `SameSite=Lax`
- セッション: `use_strict_mode`, `cookie_httponly`, ログイン時 `session_regenerate_id(true)`
- remember-me: ランダム 32 バイト、DB には SHA-256 ハッシュ保存
- パスワード: `password_hash` / `PASSWORD_DEFAULT`
- ヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- アップロード: 拡張子 + finfo MIME チェック、ランダムファイル名、`public/uploads` で PHP 無効化
- Apache: `Options -Indexes`、`public/` 外へのアクセス拒否

## 8. 対象外・許容リスク（ユーザー回答）
- なし。開発用構成（Mailhog、`display_errors`、テスト用アカウント、`.env`）、CI、サードパーティ依存の既知脆弱性もすべて対象。

## 9. 未解決の質問
- 攻撃者について「管理者は信頼済み」と「悪意のある管理者も対象」の両方が選択されたため、管理者も対象に含める方を採用した（要確認）。
- 本番の TLS 終端・リバースプロキシ・WAF の有無は不明（リポジトリ外）。
- 決済は代金引換・銀行振込のみでカード情報は扱わない前提。
