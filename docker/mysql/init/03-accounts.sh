#!/bin/bash
# Seeded accounts are created locked ('!'). Only APP_ENV=development gets the
# well-known demo password; otherwise the admin needs ADMIN_PASSWORD_HASH.
set -eu

BCRYPT_RE='^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$'
ARGON2_RE='^\$argon2id?\$v=[0-9]+\$m=[0-9]+,t=[0-9]+,p=[0-9]+\$[A-Za-z0-9+/]{22,}\$[A-Za-z0-9+/]{43,}$'

run_sql() { mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}"; }

if [[ "${APP_ENV:-production}" == "development" ]]; then
    echo "03-accounts: APP_ENV=development, enabling demo accounts"
    # SHA-512 crypt hash, accepted by PHP password_verify().
    demo_hash="$(printf '%s' 'password' | openssl passwd -6 -stdin)"
    echo "UPDATE users SET password_hash = '${demo_hash}' WHERE id IN (1, 2, 3);" | run_sql
elif [[ -n "${ADMIN_PASSWORD_HASH:-}" ]]; then
    if [[ ! "$ADMIN_PASSWORD_HASH" =~ $BCRYPT_RE && ! "$ADMIN_PASSWORD_HASH" =~ $ARGON2_RE ]]; then
        echo "03-accounts: ADMIN_PASSWORD_HASH is not a complete password_hash() bcrypt/argon2 value" >&2
        exit 1
    fi
    echo "03-accounts: setting admin password from ADMIN_PASSWORD_HASH"
    echo "UPDATE users SET password_hash = '${ADMIN_PASSWORD_HASH}' WHERE id = 1;" | run_sql
else
    echo "03-accounts: no ADMIN_PASSWORD_HASH set, seeded accounts stay locked"
fi
