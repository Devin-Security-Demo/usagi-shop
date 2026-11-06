#!/usr/bin/env bash
# Smoke test for a running stack (docker compose up -d).
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0

ok()   { PASS=$((PASS + 1)); printf '  \033[32mOK\033[0m   %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; exit 1; }

token() { grep -o 'name="_token" value="[^"]*"' "$1" | head -1 | sed 's/.*value="\([^"]*\)"/\1/'; }

login() { # jar email
  local jar="$1" page="$WORK/login.html"
  curl -fsS -c "$jar" -b "$jar" "$BASE_URL/login.php" -o "$page"
  curl -fsS -c "$jar" -b "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' \
    --data-urlencode "_token=$(token "$page")" --data-urlencode "email=$2" --data-urlencode "password=password" \
    "$BASE_URL/login.php"
}

echo "Smoke test against $BASE_URL"

for i in $(seq 1 60); do
  curl -fsS -o /dev/null "$BASE_URL/" && break
  sleep 2
  [ "$i" = 60 ] && fail "site did not come up"
done

# product list + search
curl -fsS "$BASE_URL/" -o "$WORK/index.html"
grep -q '商品一覧' "$WORK/index.html" && grep -q 'product.php?id=1' "$WORK/index.html" || fail "product list"
ok "product list"
curl -fsS "$BASE_URL/index.php?q=%E3%83%81%E3%83%A2%E3%82%B7%E3%83%BC" -o "$WORK/search.html"
grep -q 'チモシー' "$WORK/search.html" || fail "product search"
ok "product search"
curl -fsS "$BASE_URL/product.php?id=2" -o "$WORK/product.html"
grep -q 'カスタマーレビュー' "$WORK/product.html" || fail "product page"
ok "product page"

# login
USER_JAR="$WORK/user.jar"
res="$(login "$USER_JAR" user1@usagi.example)"
[[ "$res" == 303* ]] || fail "login as user1 ($res)"
curl -fsS -b "$USER_JAR" "$BASE_URL/orders.php" -o "$WORK/orders.html"
grep -q 'UO-100231' "$WORK/orders.html" || fail "my orders"
ok "login + my orders"

# shipping api
curl -fsS "$BASE_URL/api/shipping.php?zip=150-0001&subtotal=1000" | grep -q '"fee":660' || fail "shipping api"
ok "shipping api"

# place order
curl -fsS -b "$USER_JAR" -c "$USER_JAR" "$BASE_URL/product.php?id=1" -o "$WORK/p1.html"
curl -fsS -b "$USER_JAR" -c "$USER_JAR" -o /dev/null --data-urlencode "_token=$(token "$WORK/p1.html")" \
  -d action=add -d product_id=1 -d qty=2 "$BASE_URL/cart.php"
curl -fsS -b "$USER_JAR" -c "$USER_JAR" "$BASE_URL/checkout.php" -o "$WORK/checkout.html"
grep -q '国産にんじんスティック' "$WORK/checkout.html" || fail "checkout page"
redirect="$(curl -fsS -b "$USER_JAR" -c "$USER_JAR" -o /dev/null -w '%{redirect_url}' \
  --data-urlencode "_token=$(token "$WORK/checkout.html")" \
  --data-urlencode "ship_name=佐藤 花子" --data-urlencode "ship_zip=180-0004" \
  --data-urlencode "ship_address=東京都武蔵野市吉祥寺本町2-8-1" --data-urlencode "ship_phone=090-1111-2222" \
  --data-urlencode "payment=代金引換" --data-urlencode "note=" "$BASE_URL/checkout.php")"
[[ "$redirect" == *"checkout.php?done=UO-"* ]] || fail "place order ($redirect)"
curl -fsS -b "$USER_JAR" "$redirect" -o "$WORK/done.html"
grep -q 'ご注文ありがとうございました' "$WORK/done.html" || fail "order confirmation"
ok "place order (${redirect##*done=})"

# invoice download
code_type="$(curl -fsS -b "$USER_JAR" -o "$WORK/invoice.pdf" -w '%{http_code} %{content_type}' "$BASE_URL/invoice.php?file=INV-100231.pdf")"
[[ "$code_type" == "200 application/pdf"* ]] && head -c 5 "$WORK/invoice.pdf" | grep -q '%PDF-' || fail "invoice download ($code_type)"
ok "invoice download"

# admin export
ADMIN_JAR="$WORK/admin.jar"
res="$(login "$ADMIN_JAR" admin@usagi.example)"
[[ "$res" == 303* ]] || fail "login as admin ($res)"
curl -fsS -b "$ADMIN_JAR" "$BASE_URL/admin/index.php" -o "$WORK/admin.html"
grep -q '本日の注文数' "$WORK/admin.html" || fail "admin dashboard"
ok "admin dashboard"
curl -fsS -b "$ADMIN_JAR" -D "$WORK/export.h" "$BASE_URL/admin/export.php" -o "$WORK/export.csv"
grep -qi 'content-type: text/csv' "$WORK/export.h" && grep -q 'UO-100231' "$WORK/export.csv" || fail "admin export"
ok "admin csv export"

# access control
code="$(curl -sS -b "$USER_JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/admin/export.php")"
[ "$code" = 403 ] || fail "customer must not export ($code)"
ok "admin pages require admin"
curl -fsS -b "$USER_JAR" "$BASE_URL/order.php?id=1" -o "$WORK/order.html"
grep -q 'UO-100231' "$WORK/order.html" || fail "own order detail"
code="$(curl -sS -b "$USER_JAR" -o /dev/null -w '%{http_code}' "$BASE_URL/order.php?id=3")"
[ "$code" = 404 ] || fail "customer must not view another customer's order ($code)"
ok "order detail limited to owner"

echo "All $PASS checks passed."
