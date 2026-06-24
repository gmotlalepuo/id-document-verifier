#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

mkdir -p \
    storage/app/verification \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    echo "Waiting for MySQL at ${DB_HOST:-db}:${DB_PORT:-3306}..."
    php -r '
    $host = getenv("DB_HOST") ?: "db";
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE") ?: "id_document_verifier";
    $user = getenv("DB_USERNAME") ?: "id_verifier";
    $pass = getenv("DB_PASSWORD") ?: "id_verifier_password";
    $deadline = time() + 60;
    do {
        try {
            new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
            exit(0);
        } catch (Throwable $e) {
            usleep(500000);
        }
    } while (time() < $deadline);
    fwrite(STDERR, "MySQL did not become ready in time.\n");
    exit(1);
    '
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
