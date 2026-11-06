{ pkgs }:
let
  inherit (pkgs) lib;

  php = pkgs.php82.buildEnv {
    extensions = { enabled, all }: enabled ++ (with all; [ gd opcache ]);
    extraConfig = builtins.readFile ../php/99-app.ini;
  };

  phpApache = php.override {
    apxs2Support = true;
    apacheHttpd = pkgs.apacheHttpd;
  };

  composer = php.packages.composer;
  mysql = pkgs.mysql84;
  httpd = pkgs.apacheHttpd;

  httpdConf = pkgs.replaceVars ../apache/httpd.conf {
    inherit httpd;
    php = phpApache;
  };

  # Shared defaults; every value can be overridden from the environment.
  envSetup = ''
    if [ ! -f composer.json ] || [ ! -d public ]; then
      echo "error: run this from the usagi-shop repository root" >&2
      exit 1
    fi
    export APP_ROOT="''${APP_ROOT:-$PWD}"
    export STATE_DIR="''${STATE_DIR:-$APP_ROOT/.devenv}"
    export APP_PORT="''${APP_PORT:-8080}"
    export MAILHOG_PORT="''${MAILHOG_PORT:-8025}"
    export MAIL_HOST="''${MAIL_HOST:-127.0.0.1}"
    export MAIL_PORT="''${MAIL_PORT:-1025}"
    export DB_HOST="''${DB_HOST:-127.0.0.1}"
    export DB_PORT="''${DB_PORT:-3306}"
    export DB_DATABASE="''${DB_DATABASE:-usagi_shop}"
    export DB_USERNAME="''${DB_USERNAME:-usagi_app}"
    export DB_PASSWORD="''${DB_PASSWORD:-Usag1Sh0p#Db2024!}"
    export DB_SOCKET="$STATE_DIR/mysql/mysqld.sock"
    export DB_ROOT_CNF="$STATE_DIR/mysql/root.cnf"
    export TZ="''${TZ:-Asia/Tokyo}"
  '';

  mysqlStart = pkgs.writeShellApplication {
    name = "usagi-mysql";
    runtimeInputs = [ mysql pkgs.coreutils ];
    text = envSetup + ''
      datadir="$STATE_DIR/mysql/data"
      if [ ! -d "$datadir/mysql" ]; then
        rm -rf "$datadir"
        mkdir -p "$datadir"
        umask 077
        # Random root password, set during --initialize (no network listener yet).
        rootpw="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
        initsql="$STATE_DIR/mysql/init-root.sql"
        echo "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootpw';" > "$initsql"
        mysqld --no-defaults --initialize-insecure --init-file="$initsql" \
          --datadir="$datadir" --basedir=${mysql}
        rm -f "$initsql"
        printf '[client]\nuser=root\npassword=%s\n' "$rootpw" > "$DB_ROOT_CNF"
      fi
      exec mysqld --no-defaults \
        --datadir="$datadir" --basedir=${mysql} \
        --socket="$DB_SOCKET" --pid-file="$STATE_DIR/mysql/mysqld.pid" \
        --bind-address=127.0.0.1 --port="$DB_PORT" --mysqlx=OFF \
        --character-set-server=utf8mb4 --collation-server=utf8mb4_0900_ai_ci \
        --default-time-zone=+09:00
    '';
  };

  # Equivalent of docker-entrypoint-initdb.d. The marker records which DB/user
  # it was provisioned for; an interrupted seed is dropped and redone.
  dbInit = pkgs.writeShellApplication {
    name = "usagi-db-init";
    runtimeInputs = [ mysql pkgs.coreutils ];
    text = envSetup + ''
      marker="$STATE_DIR/mysql/.seeded"
      want="$(printf '%s\n%s\n%s\n' "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD" | sha256sum | cut -d' ' -f1)"
      if [ -f "$marker" ]; then
        if [ "$(cat "$marker")" = "$want" ]; then
          exit 0
        fi
        echo "error: DB_DATABASE / DB_USERNAME / DB_PASSWORD differ from the seeded database." >&2
        echo "Run 'nix run .#dev-reset' to re-create it with the new settings." >&2
        exit 1
      fi
      root() { mysql --defaults-extra-file="$DB_ROOT_CNF" --protocol=socket --socket="$DB_SOCKET" "$@"; }
      root -e "
        DROP DATABASE IF EXISTS \`$DB_DATABASE\`;
        CREATE DATABASE \`$DB_DATABASE\`;
        CREATE USER IF NOT EXISTS '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
        GRANT ALL ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost';
        CREATE USER IF NOT EXISTS '$DB_USERNAME'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
        GRANT ALL ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'127.0.0.1';"
      for f in "$APP_ROOT"/db/init/*.sql; do
        echo "loading $f"
        root "$DB_DATABASE" < "$f"
      done
      echo "$want" > "$marker"
    '';
  };

  composerInstall = pkgs.writeShellApplication {
    name = "usagi-composer-install";
    runtimeInputs = [ php composer ];
    text = envSetup + ''
      composer install --no-interaction --no-progress --prefer-dist
      mkdir -p public/uploads/avatars public/uploads/products
    '';
  };

  httpdStart = pkgs.writeShellApplication {
    name = "usagi-httpd";
    runtimeInputs = [ httpd ];
    text = envSetup + ''
      mkdir -p "$STATE_DIR/httpd"
      export PHP_INI_SCAN_DIR=${phpApache}/lib
      exec httpd -f ${httpdConf} -DFOREGROUND
    '';
  };

  mailhogStart = pkgs.writeShellApplication {
    name = "usagi-mailhog";
    runtimeInputs = [ pkgs.mailhog ];
    text = envSetup + ''
      exec MailHog \
        -smtp-bind-addr "127.0.0.1:$MAIL_PORT" \
        -ui-bind-addr "127.0.0.1:$MAILHOG_PORT" \
        -api-bind-addr "127.0.0.1:$MAILHOG_PORT"
    '';
  };

  processComposeConfig = pkgs.writeText "process-compose.json" (builtins.toJSON {
    version = "0.5";
    processes = {
      mailhog.command = lib.getExe mailhogStart;
      composer = {
        command = lib.getExe composerInstall;
        availability.restart = "exit_on_failure";
      };
      db = {
        command = lib.getExe mysqlStart;
        readiness_probe = {
          exec.command = "${mysql}/bin/mysqladmin --no-defaults --protocol=socket --socket=\"$DB_SOCKET\" ping --silent";
          initial_delay_seconds = 1;
          period_seconds = 2;
          failure_threshold = 60;
        };
        shutdown.signal = 15;
      };
      db-init = {
        command = lib.getExe dbInit;
        depends_on.db.condition = "process_healthy";
        availability.restart = "exit_on_failure";
      };
      web = {
        command = lib.getExe httpdStart;
        depends_on = {
          composer.condition = "process_completed_successfully";
          db-init.condition = "process_completed_successfully";
          mailhog.condition = "process_started";
        };
        shutdown.signal = 28; # SIGWINCH: graceful stop
      };
    };
  });

  dev = pkgs.writeShellApplication {
    name = "dev";
    runtimeInputs = [ pkgs.process-compose ];
    text = envSetup + ''
      mkdir -p "$STATE_DIR"
      echo "shop:    http://localhost:$APP_PORT"
      echo "admin:   http://localhost:$APP_PORT/admin/"
      echo "mailhog: http://localhost:$MAILHOG_PORT"
      exec process-compose up --config ${processComposeConfig} --port "''${PC_PORT:-8090}" "$@"
    '';
  };

  dev-reset = pkgs.writeShellApplication {
    name = "dev-reset";
    text = envSetup + ''
      echo "removing $STATE_DIR (database will be re-seeded on next start)"
      rm -rf "$STATE_DIR"
    '';
  };
in
{
  inherit pkgs;

  packages = { inherit php composer dev dev-reset; };

  shell = pkgs.mkShell {
    packages = [ php composer mysql httpd pkgs.mailhog pkgs.process-compose dev dev-reset ];
  };
}
