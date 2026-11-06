# usagi-shop 脅威モデル

対象: `Devin-Security-Demo/usagi-shop`（うさぎ商事オンラインショップ）。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8.4、Docker Compose 構成。
別のインフラリポジトリは無く、このリポジトリの `Dockerfile` / `docker-compose.yml` / `apache/` / `php/` がインフラ定義のすべて（ユーザー回答）。

## 1. システム概要と信頼境界

- **運用形態（ユーザー回答）**: 社内／デモ向けで、インターネットには公開していない。到達できるのは社内ネットワーク上の利用者。
- 構成（`docker-compose.yml`）: `web`（Apache + mod_php、ホスト 8080 番を公開）、`db`（MySQL、ホストには非公開）、`mailhog`（SMTP 1025 番は内部、Web UI 8025 番をホストに公開）、`composer`（起動時に依存関係をインストール）。
- 信頼境界
  1. ブラウザ → `web`（未ログイン → 一般会員 → 管理者 の権限段階）
  2. `web` → `db`（`config/database.php` の固定認証情報 `usagi_app`）
  3. `web` → 自分自身の `/api/shipping.php`（`lib/ShippingClient.php` が `config('shipping_api')` = `http://127.0.0.1/...` を Guzzle で呼び出す）
  4. `web` → `mailhog`（`lib/OrderMailer.php`、SMTP 認証・TLS なし）
  5. ファイルシステム: `public/uploads/`（Web から配信、PHP 実行は無効）、`storage/invoices/`（DocumentRoot 外）

## 2. コンポーネント

| 名前 | 範囲 |
| --- | --- |
| Storefront | `public/index.php`, `public/product.php`（レビュー投稿・表示）, `public/cart.php`, `public/about.php`, `public/404.php`, `lib/Cart.php`, `lib/views/`, `public/js/` |
| Auth | `lib/auth.php`（セッション、remember-me Cookie）, `lib/csrf.php`, `lib/bootstrap.php`, `lib/helpers.php`, `public/login.php`, `public/logout.php`, `public/account.php`（プロフィール・パスワード・アバターのアップロード） |
| Orders | `public/checkout.php`, `public/order.php`, `public/orders.php`, `public/invoice.php`, `lib/OrderMailer.php`, `storage/invoices/` |
| Shipping API | `public/api/shipping.php`, `lib/ShippingCalculator.php`, `lib/ShippingClient.php` |
| Admin | `public/admin/index.php`（ダッシュボード、商品画像のアップロードと ImageMagick `convert` 実行）, `public/admin/export.php`（注文 CSV 出力）, `lib/views/admin_*` |
| Infrastructure | `Dockerfile`, `docker-compose.yml`, `.env`, `apache/000-default.conf`, `php/99-app.ini`, `config/`, `docker/mysql/init/`, `composer.json` / `composer.lock`（Guzzle 6.5.5、PHPMailer 5.2.16）, `.github/workflows/` |

## 3. エントリポイントと到達できる人

| エントリポイント | 到達できる人 |
| --- | --- |
| `/`, `/product.php`（GET）, `/cart.php`, `/about.php`, `/login.php` | 未ログインの利用者 |
| `/api/shipping.php` | 未ログインの利用者（認証なし） + `web` 自身 |
| `/product.php`（POST レビュー）, `/checkout.php`, `/order.php`, `/orders.php`, `/invoice.php`, `/account.php` | ログイン済みの一般会員（`require_login()`） |
| `/admin/index.php`, `/admin/export.php` | 管理者（`require_admin()`） |
| `remember` Cookie（`auth_restore_from_cookie()` が全リクエストで処理） | 未ログインの利用者 |
| Mailhog UI（8025） | ホストのポートに届く人 |

会員の新規登録画面は無い（アカウントはシードされたもの）。

## 4. 資産と重要度

ユーザー回答: 以下はすべて同じくらい重要。
- 顧客の個人情報（氏名・メール・郵便番号・住所・電話）: `users`, `orders` テーブル、注文確認メール、CSV 出力
- 請求書 PDF（`storage/invoices/`）
- 管理画面と注文・在庫・商品データ
- サーバーと DB の乗っ取り（RCE、DB 認証情報 `config/database.php` / `.env`、remember トークン、セッション）
- 決済は代金引換と銀行振込のみで、カード情報は保持しない。

## 5. 攻撃者（ユーザー回答）

1. 未ログインの利用者（社内ネットワーク上）
2. ログイン済みの一般会員（他の会員のデータや管理者権限を狙う）
3. 悪意のある管理者、または乗っ取られた管理者アカウント。管理者権限からサーバーや DB へ昇格する経路（RCE など）も対象

## 6. 既存の対策

- `h()`（htmlspecialchars）で出力をエスケープ、`csrf_verify()` で POST を検証、多くのクエリは PDO のプリペアドステートメント（`ATTR_EMULATE_PREPARES=false`）
- ログイン時に `session_regenerate_id`、セッション Cookie は HttpOnly / SameSite=Lax / strict mode
- remember-me トークンは SHA-256 ハッシュで保存し、`hash_equals` で照合
- アップロード: `public/uploads` は PHP 実行が無効。アバターは拡張子 + MIME + `getimagesize` で検証し、ランダムなファイル名で保存
- Apache: `public/` の外は `Require all denied`、ディレクトリ一覧は無効
- セキュリティヘッダー: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`
- CSV 出力は式インジェクション対策済み（先頭に `'` を付与）

## 7. 対象外・受け入れ済みのリスク

ユーザー回答: **特になし（すべて対象）**。開発用の設定（`display_errors=On`、`expose_php=On`、Mailhog、リポジトリにコミットされた `.env` / `config/database.php` の認証情報）や、管理者だけが実行できる操作の問題も対象に含める。
外部に公開していないという運用形態は、到達できる攻撃者が社内ネットワークの利用者に限られることを意味するが、ユーザーは対象外にするものを指定していない。

## 8. 未確定の事項

- 将来インターネットに公開する予定はあるか（あれば、未ログインの攻撃者の範囲が広がる）
- 社内ネットワーク上で 8080 / 8025 番に到達できる人の範囲
- 本番相当の環境で、リポジトリの `.env` の認証情報を使い回していないか
