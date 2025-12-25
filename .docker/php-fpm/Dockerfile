FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    intl \
    opcache \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/var

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure PHP-FPM
COPY .docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# Optimize for production
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Clear cache
RUN php bin/console cache:clear --env=prod --no-debug
RUN php bin/console cache:warmup --env=prod --no-debug

EXPOSE 9000

CMD ["php-fpm"]
