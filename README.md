# うさぎ商事 オンラインショップ / Usagi Shoji Online Shop

株式会社うさぎ商事の、うさぎ用品を扱う小さな EC サイトです。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8 で動作します。

A small e-commerce site for rabbit supplies, built with plain PHP 8.2, Apache mod_php and MySQL 8.

![ストアトップ / Storefront](docs/screenshots/01-storefront.png)

## 起動方法 / Getting started

```bash
cp .env.example .env   # MYSQL_PASSWORD / MYSQL_ROOT_PASSWORD に強いパスワードを設定（空だと起動しません）/ set strong passwords (required)
docker compose up -d
```

DB の認証情報は `.env`（Git 管理外）から環境変数として渡されます。`.env` はコミットしないでください。
DB credentials are passed as environment variables from `.env`, which is git-ignored. Never commit it.

| URL | 内容 / What |
| --- | --- |
| http://localhost:8080 | ショップ / Shop |
| http://localhost:8080/admin/ | 管理画面 / Admin |
| http://localhost:8025 | Mailhog（注文確認メール / order mails） |

初回起動時に Composer の依存関係インストールと DB の初期データ投入が自動で行われます。
On first start, Composer dependencies are installed and the database is seeded automatically.

## テスト用アカウント / Test accounts

パスワードはすべて `password` / All passwords are `password`.

| メール / Email | 権限 / Role |
| --- | --- |
| `admin@usagi.example` | 管理者 / Admin |
| `user1@usagi.example` | 一般会員（佐藤 花子）/ Customer |
| `user2@usagi.example` | 一般会員（鈴木 一郎）/ Customer |

## テスト / Tests

```bash
tests/smoke.sh                                   # curl smoke test (stack must be running)
docker run --rm -v "$PWD":/app -w /app composer:2 \
  sh -c "composer install -q && vendor/bin/phpunit"   # PHPUnit unit tests
```

## スクリーンショット / Screenshots

| 商品詳細 / Product | 管理画面 / Admin |
| --- | --- |
| ![商品詳細](docs/screenshots/02-product.png) | ![管理画面](docs/screenshots/12-admin-dashboard.png) |

その他の画面は [`docs/screenshots/`](docs/screenshots/) を参照してください。 / More in `docs/screenshots/`.
