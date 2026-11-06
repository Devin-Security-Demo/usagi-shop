# うさぎ商事 オンラインショップ / Usagi Shoji Online Shop

株式会社うさぎ商事の、うさぎ用品を扱う小さな EC サイトです。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8 で動作します。

A small e-commerce site for rabbit supplies, built with plain PHP 8.2, Apache mod_php and MySQL 8.

![ストアトップ / Storefront](docs/screenshots/01-storefront.png)

## 起動方法 / Getting started

開発環境は [Nix flake](flake.nix) で管理しています（Docker は不要）。Nix で flakes を有効にしてから、リポジトリ直下で実行してください。
The dev environment is a [Nix flake](flake.nix) (no Docker). With flakes enabled, run from the repository root:

```bash
nix run            # PHP 8.2 + Apache mod_php, MySQL 8.4, MailHog を process-compose で起動
nix run .#dev-reset   # DB などの状態 (.devenv/) を削除 / wipe local state (.devenv/)
nix develop        # php, composer, mysql, httpd などが使えるシェル / shell with the toolchain
```

| URL | 内容 / What |
| --- | --- |
| http://localhost:8080 | ショップ / Shop |
| http://localhost:8080/admin/ | 管理画面 / Admin |
| http://localhost:8025 | Mailhog（注文確認メール / order mails） |

初回起動時に Composer の依存関係インストールと DB の初期データ投入（`db/init/*.sql`）が自動で行われます。データは `.devenv/` に保存されます。ポートは `APP_PORT` / `MAILHOG_PORT` / `DB_PORT` 環境変数で変更できます。
On first start, Composer dependencies are installed and the database is seeded from `db/init/*.sql`. State lives in `.devenv/`. Ports can be changed with `APP_PORT` / `MAILHOG_PORT` / `DB_PORT`.

PHP 8.2 は nixpkgs で EOL 扱いのためバイナリキャッシュがなく、初回のみ PHP のビルドに数分かかります。
PHP 8.2 is not in the binary cache (EOL in nixpkgs), so the first build compiles PHP and takes a few minutes.

## テスト用アカウント / Test accounts

パスワードはすべて `password` / All passwords are `password`.

| メール / Email | 権限 / Role |
| --- | --- |
| `admin@usagi.example` | 管理者 / Admin |
| `user1@usagi.example` | 一般会員（佐藤 花子）/ Customer |
| `user2@usagi.example` | 一般会員（鈴木 一郎）/ Customer |

## テスト / Tests

```bash
tests/smoke.sh                       # curl smoke test (stack must be running: nix run)
nix develop -c vendor/bin/phpunit    # PHPUnit unit tests
```

## スクリーンショット / Screenshots

| 商品詳細 / Product | 管理画面 / Admin |
| --- | --- |
| ![商品詳細](docs/screenshots/02-product.png) | ![管理画面](docs/screenshots/12-admin-dashboard.png) |

その他の画面は [`docs/screenshots/`](docs/screenshots/) を参照してください。 / More in `docs/screenshots/`.
