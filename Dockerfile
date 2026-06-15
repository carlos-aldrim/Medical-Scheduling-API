FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip nginx libpq-dev libzip-dev librabbitmq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .
RUN touch /var/www/.env

RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

COPY docker/nginx.render.conf /etc/nginx/sites-available/default

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

RUN mkdir -p /var/www/var && chown -R www-data:www-data /var/www/var

EXPOSE 10000

CMD ["/start.sh"]
