set -e

export APP_ENV=prod

php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "==> Criando admin padrão (se não existir)..."
php bin/console app:create-admin --env=prod

php-fpm -D
nginx -g "daemon off;"
