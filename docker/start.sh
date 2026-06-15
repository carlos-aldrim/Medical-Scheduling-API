set -e

export APP_ENV=prod

echo "==> Gerando chaves JWT..."
mkdir -p /var/www/config/jwt
if [ ! -f /var/www/config/jwt/private.pem ]; then
    openssl genrsa -out /var/www/config/jwt/private.pem 2048
    openssl rsa -pubout -in /var/www/config/jwt/private.pem -out /var/www/config/jwt/public.pem
    chmod 644 /var/www/config/jwt/private.pem /var/www/config/jwt/public.pem
    echo "    Chaves geradas."
else
    echo "    Chaves já existem."
fi

echo "==> Limpando e aquecendo cache..."
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "==> Ajustando permissões..."
chown -R www-data:www-data /var/www/var
chmod -R 775 /var/www/var

echo "==> Rodando migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "==> Criando admin padrão (se não existir)..."
php bin/console app:create-admin --env=prod

echo "==> Iniciando php-fpm..."
php-fpm -D

echo "==> Iniciando nginx..."
nginx -g "daemon off;"
