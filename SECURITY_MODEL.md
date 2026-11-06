# 脅威モデル: うさぎ商事オンラインショップ（Devin-Security-Demo/usagi-shop）

## 1. システム概要
- うさぎ用品の小規模 EC サイト。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4。注文確認メールは SMTP（開発時は Mailhog）。
- デプロイ構成は同リポジトリ内の `Dockerfile`、`docker-compose.yml`（web / composer / db / mailhog）、`apache/000-default.conf`、`php/99-app.ini`。専用のインフラリポジトリ（Terraform / Kubernetes 等）はなし（`git_list_repos` で確認）。本番環境の実構成はこれと異なる可能性がある。
- 運用環境: **ユーザー回答**: インターネットに公開された本番環境として扱う。

## 2. 信頼境界
1. インターネット → Apache（`DocumentRoot /var/www/html/public`、`public/` 外は `Require all denied`）
2. 匿名 → ログイン済み会員（`require_login()`、`lib/auth.php`）
3. 会員 → 管理者（`require_admin()`、`users.role = 'admin'`）
4. Web アプリ → 内部サービス（MySQL `db:3306`、SMTP `mailhog:1025`、自身の配送 API `http://127.0.0.1/api/shipping.php` を `lib/ShippingClient.php` が Guzzle で呼ぶ）
5. アップロードファイル → サーバー（`public/uploads/` は `php_admin_flag engine off`、管理画面の商品画像は `exec("convert ...")` で変換）

## 3. コンポーネント
| 名前 | 対象コード |
| --- | --- |
| ストアフロント | `public/index.php`, `product.php`, `cart.php`, `checkout.php`, `orders.php`, `order.php`, `invoice.php`, `account.php`, `about.php`, `404.php`, `lib/Cart.php`, `lib/helpers.php`, `lib/views/*`, `public/js/*` |
| 認証・セッション | `lib/auth.php`, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| 管理画面 | `public/admin/index.php`, `public/admin/export.php`, `lib/views/admin_*` |
| 配送API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| メール送信 | `lib/OrderMailer.php` |
| データベース | `lib/db.php`, `config/database.php`, `docker/mysql/init/*.sql` |
| インフラ・依存関係 | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/`, `.env`, `config/app.php`, `composer.json`/`composer.lock`, `.github/workflows/` |

## 4. エントリポイント
- 匿名: `/`（検索 `q`）、`/product.php?id=`、`/cart.php`（POST）、`/login.php`、`/about.php`、`/api/shipping.php?zip=&subtotal=`（認証なし）、`remember` Cookie（`auth_restore_from_cookie()` が全リクエストで `unserialize()`）
- 会員: `/checkout.php`（注文作成・`?done=`）、`/orders.php`、`/order.php?id=`、`/invoice.php?file=`、`/account.php`（プロフィール・パスワード・アバター）、`/product.php`（レビュー POST）、`/logout.php`
- 管理者: `/admin/`（ダッシュボード・商品画像アップロード）、`/admin/export.php`（全注文 CSV）
- 公開ポート（compose）: web `8080`、Mailhog UI `8025`（認証なし、全注文確認メールを閲覧可能）

## 5. 資産と重要度
- **最重要（ユーザー回答）**: 管理者アカウント・セッションとサーバー（RCE）
- **最重要（ユーザー回答）**: 会員の個人情報・注文情報（氏名・メール・住所・電話番号、注文履歴、請求書 PDF `storage/invoices/`、CSV エクスポート `/admin/export.php`）
- 高（Devin の読み）: DB 認証情報（`config/database.php`, `.env`）
- 中（Devin の読み）: 価格・在庫・送料の正しさ、商品・レビュー表示の完全性

## 6. 攻撃者
- **ユーザー回答**: 想定する攻撃者は「匿名のインターネット利用者」のみ。ログイン済みの一般会員・悪意のある管理者は攻撃者として選択されなかった。
- 新規会員登録機能はない（`users` への INSERT はシードのみ）。`public/login.php` が表示するデモ用アカウント（`admin@usagi.example` 等、パスワード `password`）は、**ユーザー回答**により本番には存在しない。したがって匿名利用者は会員・管理者の資格情報を持たない前提とする。
- 会員または管理者としてのログインを前提とする指摘は、匿名利用者がその権限に到達する経路（下記）がスキャナーにより確認されていない限り優先度を下げる。
- 匿名利用者が CSRF・XSS・セッション乗っ取り・SQL インジェクション等で会員・管理者の権限に到達する経路は、引き続き対象。

## 7. 既存の対策（コードから確認）
- SQL: PDO プリペアドステートメント（ただし `public/login.php` は文字列連結）
- 出力: `h()`（`htmlspecialchars` ENT_QUOTES）
- CSRF: セッショントークン + `hash_equals`（`csrf_verify()`）、Cookie `SameSite=Lax`
- セッション: `use_strict_mode`、`cookie_httponly`、ログイン時 `session_regenerate_id(true)`（`secure` フラグは未設定）
- remember-me: ランダム 32 バイト、DB には SHA-256 ハッシュ保存（Cookie は PHP `serialize` 形式）
- パスワード: `password_hash` / `PASSWORD_DEFAULT`、メール・パスワード変更時は現在のパスワードを要求
- ヘッダー: `X-Content-Type-Options`、`X-Frame-Options: SAMEORIGIN`、`Referrer-Policy`
- アップロード: 拡張子 + finfo MIME チェック、ランダムファイル名、`public/uploads` で PHP 無効化
- CSV エクスポート: 先頭 `= + - @` 等をエスケープ（数式インジェクション対策）
- Apache: `Options -Indexes`、`public/` 外へのアクセス拒否

## 8. 対象外・許容リスク
- **ユーザー回答**: なし。開発用構成（Mailhog、`display_errors`、デモ用アカウント、`.env`/`config` の認証情報）、CI、サードパーティ依存の既知脆弱性もすべて対象。

## 9. 未解決の質問
- 本番の実デプロイ構成（TLS 終端、Mailhog の有無、ポート公開）がリポジトリの compose と同じか。
