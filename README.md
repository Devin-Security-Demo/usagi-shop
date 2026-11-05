# うさぎ商事 オンラインショップ / Usagi Shoji Online Shop

株式会社うさぎ商事の、うさぎ用品を扱う小さな EC サイトです。PHP 8.2（フレームワークなし）+ Apache mod_php + MySQL 8 で動作します。

A small e-commerce site for rabbit supplies, built with plain PHP 8.2, Apache mod_php and MySQL 8.

![ストアトップ / Storefront](docs/screenshots/01-storefront.png)

## 起動方法 / Getting started

```bash
docker compose up -d
```

| URL | 内容 / What |
| --- | --- |
| http://localhost:8080 | ショップ / Shop |
| http://localhost:8080/admin/ | 管理画面 / Admin |
| http://localhost:8025 | Mailhog（注文確認メール / order mails） |

初回起動時に Composer の依存関係インストールと DB の初期データ投入が自動で行われます。
On first start, Composer dependencies are installed and the database is seeded automatically.

## アカウント / Accounts

初期データのアカウントにはログイン可能なパスワードが設定されていません。初回起動後、ランダムなパスワードを発行してください（パスワードは一度だけ表示されます）。
Seeded accounts have no usable password. After the first start, issue a random one (printed once):

```bash
docker compose exec web php /var/www/html/bin/set-password.php admin@usagi.example
```

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
