FROM php:8.2-fpm-alpine

# install required extensions
RUN docker-php-ext-install pdo pdo_mysql json openssl curl mbstring

# install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# install project dependencies
RUN composer install --no-dev --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]
