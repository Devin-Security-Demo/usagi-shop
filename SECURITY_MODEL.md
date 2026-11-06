# 脅威モデル: うさぎ商事オンラインショップ（Devin-Security-Demo/usagi-shop）

## 1. システム概要
- うさぎ用品の小規模 EC サイト。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4。注文確認メールは PHPMailer（`lib/OrderMailer.php`）で SMTP 送信（Mailhog `mailhog:1025`）。
- デプロイ構成はリポジトリ内の `Dockerfile`、`docker-compose.yml`（web / composer / db / mailhog）、`apache/000-default.conf`、`php/99-app.ini` のみ。**別のインフラリポジトリはない（ユーザー回答）**。
- **社内ネットワーク・ローカルのみで動かすデモ・検証用環境（ユーザー回答）**。インターネットには公開していない。公開ポートは web `8080`、Mailhog UI `8025`。
- 決済は代金引換・銀行振込のみで、カード情報は扱わない（コードからの読み取り: `public/checkout.php` の `$payments`）。

## 2. 信頼境界
1. 社内ネットワーク → Apache（`DocumentRoot /var/www/html/public`。`public/` 外は `Require all denied`）
2. 匿名 → ログイン済み会員（`require_login()`、`lib/auth.php`）
3. 会員 → 管理者（`require_admin()`、`users.role = 'admin'`）
4. Web アプリ → 内部サービス（MySQL `db:3306`、SMTP `mailhog:1025`、自身の配送 API `http://127.0.0.1/api/shipping.php` を `lib/ShippingClient.php` が呼ぶ）
5. アップロードファイル → サーバー（`public/uploads/` は `php_admin_flag engine off`。管理画面の商品画像は `sys_get_temp_dir()/usagi-upload` に元のファイル名で保存し ImageMagick `convert` を `exec()`）

## 3. コンポーネント
| 名前 | 対象コード |
| --- | --- |
| ストアフロント | `public/index.php`, `product.php`, `cart.php`, `checkout.php`, `orders.php`, `order.php`, `invoice.php`, `account.php`, `about.php`, `404.php`, `lib/Cart.php`, `lib/helpers.php`, `lib/views/header.php`, `lib/views/footer.php`, `public/js/*`, `storage/invoices/` |
| 認証・セッション | `lib/auth.php`, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| 管理画面 | `public/admin/index.php`, `public/admin/export.php`, `lib/views/admin_*` |
| 配送API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| メール送信 | `lib/OrderMailer.php` |
| データベース | `lib/db.php`, `config/database.php`, `docker/mysql/init/*.sql` |
| インフラ・依存関係 | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/`, `.env`, `config/app.php`, `composer.json` / `composer.lock`, `.github/workflows/` |

## 4. エントリポイント
- 匿名: `/`（検索 `q`）、`/product.php?id=`、`/cart.php`（POST）、`/login.php`（POST）、`/about.php`、`/api/shipping.php?zip=&subtotal=`（認証なし）、`remember` Cookie（`auth_restore_from_cookie()` が全リクエストで処理）
- 会員: `/checkout.php`（注文作成、`?done=`）、`/orders.php`、`/order.php?id=`、`/invoice.php?file=`、`/account.php`（プロフィール・パスワード・アバター）、`/product.php`（レビュー POST）、`/logout.php`
- 管理者: `/admin/`（ダッシュボード、商品画像アップロード）、`/admin/export.php`（全注文 CSV）
- その他: Mailhog UI（`8025`、認証なし。注文メールが閲覧できる）

## 5. 資産と重要度（ユーザー回答: すべて同程度に重要）
- 会員の個人情報（氏名・メール・住所・電話番号）
- 注文データと請求書 PDF（`storage/invoices/`）、CSV エクスポート、Mailhog 内の注文メール
- 会員・管理者のアカウント（パスワードハッシュ、セッション、remember-me トークン）
- Web サーバーとデータベース（RCE、DB 認証情報 `config/database.php` / `.env`）
- 補助的: 価格・在庫・送料の正しさ、商品・レビュー表示の完全性
- → 資産の種類を理由に優先度を上げ下げしない。

## 6. 攻撃者（ユーザー回答: すべて想定）
- 未ログインの攻撃者（環境は社内ネットワーク限定のため、実際には社内ネットワークに到達できる者）
- ログイン済みの一般会員（他会員の注文・個人情報、管理機能を狙う）
- 悪意のある管理者・乗っ取られた管理者アカウント（管理者も信頼しない。管理画面からのサーバー侵害も対象）
- → 攻撃者の種類を理由に優先度を下げない。

## 7. 既存の対策（コードから確認）
- SQL: 大半は PDO プリペアドステートメント、`ATTR_EMULATE_PREPARES=false`（`lib/db.php`）
- 出力: `h()`（`htmlspecialchars` ENT_QUOTES | ENT_SUBSTITUTE）
- CSRF: セッショントークン + `hash_equals`（`csrf_verify()`）、セッション Cookie `SameSite=Lax`
- セッション: `use_strict_mode`, `cookie_httponly`、ログイン時 `session_regenerate_id(true)`。`cookie_secure` は未設定（HTTP 運用）
- remember-me: ランダム 32 バイト、DB には SHA-256 ハッシュ保存、Cookie は PHP の `serialize()` 形式
- パスワード: `password_hash` / `PASSWORD_DEFAULT`、資格情報変更時は現在のパスワードを確認
- ヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- アップロード: 拡張子 + finfo MIME チェック、ランダムなファイル名、`public/uploads` で PHP 無効化
- CSV エクスポート: 先頭文字のエスケープ（数式インジェクション対策）
- Apache: `Options -Indexes`、`public/` 外へのアクセス拒否

## 8. 対象外・許容リスク（ユーザー回答）
- なし。開発用設定（Mailhog、`display_errors`、デモ用アカウント、リポジトリ内の `.env` / `config/database.php` の資格情報）、CI も対象のまま。

## 9. 再ランクの方針
- ユーザーが示した事実は「社内ネットワーク・ローカル限定のデモ環境」のみで、資産・攻撃者・対象外による絞り込みはない。したがって基本はスキャナーの重大度を維持する。
- 社内限定であることは、インターネットに公開されていることだけが前提の問題（インターネット規模のブルートフォース・DoS、検索エンジン経由の露出など）に限って 1 段階下げる根拠とする。社内ネットワーク上の攻撃者や会員・管理者から到達できる問題は下げない。

## 10. 未解決の質問
- 将来インターネットに公開する予定はあるか（あれば 9 の引き下げは撤回する）。
- 実在の顧客データを投入しているか（デモデータのみか）。
