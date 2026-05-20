FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor \
    && docker-php-ext-install pdo_pgsql \
    bcmath \
    sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --optimize-autoloader --no-dev

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

COPY .docker/supervisor.conf /etc/supervisor/conf.d/laravel-worker.conf

CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
