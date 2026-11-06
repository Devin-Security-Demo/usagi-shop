# usagi-shop 脅威モデル

対象: `Devin-Security-Demo/usagi-shop`（株式会社うさぎ商事のうさぎ用品 EC サイト）。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4、Docker Compose で起動。

## 1. システム概要と信頼境界

- ブラウザ → Apache（`apache/000-default.conf`、DocumentRoot は `public/`）→ PHP スクリプト（`public/*.php` が個別のエントリポイント、共通処理は `lib/bootstrap.php`）→ MySQL（`db` コンテナ）。
- 送料計算はアプリ内部の HTTP 呼び出し: `lib/ShippingClient.php` が `config/app.php` の `shipping_api`（`http://127.0.0.1/api/shipping.php`）を Guzzle で叩く。
- 注文確認メールは `lib/OrderMailer.php`（PHPMailer）から Mailhog（`mailhog:1025`、UI は 8025 番で公開）へ送信。
- 信頼境界: (a) 匿名ブラウザ ↔ Web、(b) 一般会員 ↔ 他会員のデータ、(c) 一般会員 ↔ 管理者（`require_admin()`）、(d) Web コンテナ ↔ OS/シェル（管理画面の画像変換 `exec("convert ...")`）、(e) Web ↔ DB / Mailhog。

**デプロイ（ユーザー回答）**: 社内・デモ用であり、インターネットには公開していない。リポジトリ外の本番インフラはない。到達できるのは社内ネットワーク上の利用者。

## 2. コンポーネント

| Component | 範囲（コード） |
| --- | --- |
| Storefront | `public/index.php`（商品検索）, `public/product.php`（商品詳細・レビュー投稿）, `public/cart.php`, `public/about.php`, `public/404.php`, `lib/Cart.php`, `lib/views/*`, `public/js/*`, `public/css/*` |
| Auth | `lib/auth.php`（セッション・remember-me Cookie）, `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| Account & Orders | `public/account.php`（プロフィール・パスワード・アバターアップロード）, `public/checkout.php`, `public/orders.php`, `public/order.php`, `public/invoice.php`, `lib/OrderMailer.php`, `storage/invoices/` |
| Admin | `public/admin/index.php`（ダッシュボード・商品画像アップロード／変換）, `public/admin/export.php`（注文 CSV）, `lib/views/admin_*` |
| Shipping API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| Infrastructure | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/99-app.ini`, `config/`, `.env`, `docker/mysql/init/`, `.github/workflows/ci.yml`, `composer.json` / `composer.lock` |

## 3. エントリポイントと到達者

| エントリポイント | 到達者 |
| --- | --- |
| `/`, `/product.php`（GET）, `/cart.php`, `/about.php`, `/api/shipping.php`, `/login.php` | 匿名 |
| `/product.php`（レビュー POST）, `/account.php`, `/checkout.php`, `/orders.php`, `/order.php?id=`, `/invoice.php?file=`, `/logout.php` | ログイン会員（`require_login()`） |
| `/admin/index.php`, `/admin/export.php` | 管理者（`require_admin()`） |
| `remember` Cookie（`auth_restore_from_cookie()` が全リクエストで処理） | 匿名（Cookie を送れる誰でも） |
| `/uploads/avatars/*`, `/uploads/products/*` | 匿名（静的配信、PHP 実行は Apache で無効化） |
| Mailhog UI（8025）, Web（8080） | ホストに到達できるネットワーク上の誰でも |

会員登録画面はなく、アカウントはシードデータ（`docker/mysql/init/02-seed.sql`）で作成される。

## 4. 資産とデータの機密性

ユーザー回答: 特に優先する資産はなく、すべて同等に扱う。

- 顧客個人情報: `users`（氏名・メール・郵便番号・住所・電話）、`orders`（配送先氏名・住所・電話・備考）。
- 認証情報: パスワードハッシュ、セッション、`remember_tokens`（SHA-256 ハッシュ保存）。
- 管理者権限と Web/DB サーバー（コマンド実行・DB 認証情報 `config/database.php`）。
- 注文・価格・在庫・送料の整合性。決済は代金引換・銀行振込のみで、カード情報は扱わない。
- 請求書 PDF（`storage/invoices/`）。
- 注文確認メール（Mailhog）。

## 5. 攻撃者プロファイル

ユーザー回答: すべての攻撃者を対象とする（匿名・一般会員・管理者を含む）。

- 匿名の利用者（インターネット非公開のため、社内ネットワーク上の利用者）。
- ログイン済みの一般会員（他人の注文・個人情報・請求書を狙う、管理者権限への昇格）。
- 管理者（管理画面からのサーバー侵害など、管理者権限を超える影響）。
- CSRF / XSS などで被害者のブラウザを悪用する第三者。

## 6. 既存の対策

- SQL: PDO プリペアドステートメント、`ATTR_EMULATE_PREPARES=false`（`lib/db.php`）。
- 出力エスケープ: `h()`（`htmlspecialchars`, `lib/helpers.php`）。
- CSRF: `csrf_verify()`（`lib/csrf.php`）を POST ハンドラで使用。
- セッション: `session.use_strict_mode`, `cookie_httponly`, `SameSite=Lax`, ログイン時 `session_regenerate_id`（`php/99-app.ini`, `lib/auth.php`）。
- パスワード: `password_hash()`、変更時に remember トークン削除。
- アップロード: 拡張子＋`finfo` MIME 照合、ランダムファイル名、`public/uploads` での PHP 実行無効化（`apache/000-default.conf`）。
- ヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`（`lib/bootstrap.php`）。
- CSV インジェクション対策（`public/admin/export.php`）。
- Apache: アプリルート全体を `Require all denied`、`public/` のみ公開。

## 7. 対象外・受容リスク

ユーザー回答: なし。デモ用認証情報（`config/database.php`, `.env`, テストアカウント）や開発用設定（`display_errors=On`, `expose_php=On`, Mailhog）も含め、すべて報告対象。

ただし、インターネットに公開していない（社内・デモ用）ことは、リモートの匿名攻撃者による悪用の可能性を判断する際の前提とする。

## 8. 未解決の質問

- 各コンポーネントの担当チーム（`担当チーム` タグの値）。未回答の場合はタグを付けない。
- 将来インターネットに公開する予定があるか。
