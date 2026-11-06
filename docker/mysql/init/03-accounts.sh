#!/bin/bash
# Seeded accounts are created locked ('!'). Only APP_ENV=development gets the
# well-known demo password; otherwise the admin needs ADMIN_PASSWORD_HASH.
set -eu

DEMO_HASH='$2y$10$VAkPcm.cBxpQGeJffNmKZeZOI0f5c6h6X3feF1PEYGS8XkzSSW14G'
run_sql() { mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}"; }

if [ "${APP_ENV:-production}" = "development" ]; then
    echo "03-accounts: APP_ENV=development, enabling demo accounts"
    echo "UPDATE users SET password_hash = '${DEMO_HASH}' WHERE id IN (1, 2, 3);" | run_sql
elif [ -n "${ADMIN_PASSWORD_HASH:-}" ]; then
    case "$ADMIN_PASSWORD_HASH" in
        \$2y\$*|\$argon2*) ;;
        *) echo "03-accounts: ADMIN_PASSWORD_HASH is not a password_hash() value" >&2; exit 1 ;;
    esac
    case "$ADMIN_PASSWORD_HASH" in
        *"'"*|*\\*) echo "03-accounts: ADMIN_PASSWORD_HASH contains invalid characters" >&2; exit 1 ;;
    esac
    echo "03-accounts: setting admin password from ADMIN_PASSWORD_HASH"
    echo "UPDATE users SET password_hash = '${ADMIN_PASSWORD_HASH}' WHERE id = 1;" | run_sql
else
    echo "03-accounts: no ADMIN_PASSWORD_HASH set, seeded accounts stay locked"
fi
