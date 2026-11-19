#!/bin/sh
set -eu

cd /var/www/html

mkdir -p var/cache var/log var/share config/jwt

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ -n "${DATABASE_URL:-}" ]; then
    echo "[entrypoint] Waiting for the database..."
    attempts=0
    until php -r '
        $parts = parse_url((string) getenv("DATABASE_URL"));
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=postgres", $parts["host"] ?? "database", $parts["port"] ?? 5432);
        new PDO($dsn, $parts["user"] ?? "app", $parts["pass"] ?? "app");
    ' 2>/dev/null; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 60 ]; then
            echo "[entrypoint] The database did not become available in time." >&2
            exit 1
        fi
        sleep 2
    done

    php bin/console doctrine:database:create --if-not-exists --no-interaction >/dev/null 2>&1 || true
fi

echo "[entrypoint] Ensuring JWT key pair..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

echo "[entrypoint] Applying database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "${LOAD_FIXTURES:-0}" = "1" ] && [ ! -f var/.fixtures_loaded ]; then
    echo "[entrypoint] Loading demo fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction
    touch var/.fixtures_loaded
fi

echo "[entrypoint] Warming up the cache..."
php bin/console cache:warmup --no-interaction >/dev/null 2>&1 || true

