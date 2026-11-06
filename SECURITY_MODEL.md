# usagi-shop threat model

Repository: `Devin-Security-Demo/usagi-shop` — うさぎ商事オンラインショップ, a small e-commerce site for rabbit supplies. Plain PHP 8.2 (no framework) on Apache mod_php, MySQL 8.4, PHPMailer → Mailhog, Guzzle. No separate infrastructure repository exists; the deployment picture comes from the in-repo `Dockerfile`, `docker-compose.yml`, `apache/000-default.conf` and `php/99-app.ini`. A previous `SECURITY_MODEL.md` was removed in PR #3; this document does not depend on it.

## User-provided facts (these drive reranking)

- **Deployment:** demo / local-only app, not deployed to the internet.
- **Scope:** nothing is out of scope and nothing is accepted risk. The local-only deployment is therefore *not* a reason to lower a finding: judge the code as if it were reachable by the attackers below.
- **Attackers:** all of them matter — anonymous visitors, logged-in customers attacking other customers, and a malicious or compromised admin.
- **Most critical assets:** (1) customer PII — names, emails, shipping addresses, phone numbers, invoices; (2) admin access / store operations.

## System overview and trust boundaries

```
Browser ──HTTP :8080──> Apache + mod_php (web container, DocumentRoot public/)
                          │  PDO (usagi_app)          ├─ SMTP :1025 → mailhog (UI :8025)
                          ▼                           └─ HTTP 127.0.0.1/api/shipping.php (self-call via Guzzle)
                        MySQL 8.4 (db container, volume dbdata)
```

Trust boundaries: browser ↔ web app (all `$_GET`/`$_POST`/`$_COOKIE`/`$_FILES`); anonymous ↔ customer (`require_login()`); customer ↔ customer (per-record ownership checks on orders/invoices); customer ↔ admin (`require_admin()`); web app ↔ OS shell (`exec()` in admin image upload); web app ↔ MySQL; web container ↔ Mailhog (exposed on host port 8025 without auth).

## Components

| Component | Code it covers |
| --- | --- |
| **Storefront** | `public/index.php`, `public/product.php` (product page + review posting), `public/about.php`, `public/404.php`, `public/cart.php`, `lib/Cart.php`, `lib/views/header.php`, `lib/views/footer.php`, `public/js/`, `public/css/` |
| **Auth & Session** | `lib/auth.php` (session, remember-me cookie), `lib/csrf.php`, `lib/bootstrap.php`, `public/login.php`, `public/logout.php` |
| **Account & Orders** | `public/account.php` (profile, password, avatar upload → `public/uploads/avatars/`), `public/checkout.php`, `public/orders.php`, `public/order.php`, `public/invoice.php`, `storage/invoices/` |
| **Admin** | `public/admin/index.php` (dashboard, product image upload → `public/uploads/products/`, ImageMagick `convert` via `exec`), `public/admin/export.php` (orders CSV), `lib/views/admin_*.php` |
| **Shipping & Mail** | `public/api/shipping.php` (unauthenticated JSON), `lib/ShippingCalculator.php`, `lib/ShippingClient.php`, `lib/OrderMailer.php` |
| **Infrastructure & Config** | `Dockerfile`, `docker-compose.yml`, `apache/`, `php/99-app.ini`, `config/`, `.env`, `docker/mysql/init/`, `composer.json`/`composer.lock` (Guzzle 6.5.5, PHPMailer 5.2.16), `.github/workflows/ci.yml`, `lib/helpers.php`, `lib/db.php` |

## Entry points and who reaches them

- **Anonymous:** `/`, `/index.php?q=` (search), `/product.php?id=`, `/about.php`, `/cart.php` (POST add/update/remove, CSRF-protected), `/login.php` (POST), `/api/shipping.php?zip=&subtotal=`, static files incl. `/uploads/**`; the `remember` cookie is processed on every request by `auth_restore_from_cookie()` before any login check.
- **Customer (`require_login()`):** `/account.php` (profile, password, avatar), `/checkout.php` (+ `?done=`), `/orders.php`, `/order.php?id=`, `/invoice.php?file=`, POST reviews on `/product.php`, `/logout.php`.
- **Admin (`require_admin()`):** `/admin/index.php` (dashboard, product image upload), `/admin/export.php`.
- **Host network:** Mailhog UI on `:8025` (every order confirmation email, i.e. customer names/addresses), MySQL only on the compose network.

## Assets and data sensitivity

- **Critical (user-named):** customer PII in `users` and `orders` (name, email, zip, address, phone), invoice PDFs in `storage/invoices/`, order confirmation emails in Mailhog; admin accounts and anything that yields admin-equivalent control (admin session, remember token, code execution in the web container).
- **High:** password hashes and `remember_tokens`; DB credentials in `config/database.php` / `.env`; server code execution (reaches all of the above).
- **Medium:** order/price integrity (totals, shipping fees, stock), product catalogue and reviews.

## Attacker profiles

1. **Anonymous visitor** — any HTTP request, can set arbitrary cookies, can lure logged-in users to pages (CSRF/XSS).
2. **Customer** — holds a valid account (seeded `user1`/`user2`), targets other customers' orders, invoices and PII, or escalates to admin.
3. **Malicious or compromised admin** — in scope per the user: admin-only paths to OS command execution, file system access or data beyond the admin UI still matter.

## Existing controls

- `h()` output encoding (`htmlspecialchars`, ENT_QUOTES) used throughout views.
- PDO with `ATTR_EMULATE_PREPARES=false`; most queries prepared (exception: the login query in `public/login.php` concatenates `email`).
- CSRF token on every POST (`csrf_verify()`), `SameSite=Lax`, `HttpOnly` session cookie, `session.use_strict_mode`, `session_regenerate_id()` on login.
- Remember-me tokens stored as SHA-256 hashes; compared with `hash_equals` (cookie payload itself is PHP-`serialize`d and passed to `unserialize()`).
- Upload checks: extension + `finfo` MIME allow-list (avatar also `getimagesize`, 2 MB cap); random server-side filenames; Apache disables PHP in `public/uploads/`.
- Ownership checks: `orders.php` and `checkout.php?done=` filter by `user_id`; `order.php` and `invoice.php` only require login.
- Security headers: `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`. CSV formula-injection escaping in `admin/export.php`.
- Weak spots in config (not accepted risk): `display_errors=On`, `expose_php=On`, committed DB/root passwords, demo credentials printed on the login page, no TLS / no `Secure` cookies, Mailhog exposed, outdated jQuery 1.12.4, Guzzle 6.5.5, PHPMailer 5.2.16.

## Out of scope / accepted risk

None (user: "nothing is out of scope").

## Reranking guidance

Since the user rules nothing out and every attacker matters, findings keep the scanner's severity by default. A finding may be raised only when the scanner confirmed a practical path from an in-scope attacker to customer PII or to admin access. The local-only deployment does not lower anything.

## Open questions

- Is the Mailhog UI (`:8025`) considered part of the system (it holds every order email)? Assumed yes.
- Are the seeded demo accounts/passwords intended to remain in any shared environment? Assumed they are part of the demo, but still in scope.
