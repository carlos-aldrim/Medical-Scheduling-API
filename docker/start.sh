set -e

export APP_ENV=prod

php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

php bin/console doctrine:migrations:migrate --no-interaction --env=prod

php-fpm -D
nginx -g "daemon off;"
