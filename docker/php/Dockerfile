FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    zip \
    unzip \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    rabbitmq-c-dev \
    linux-headers \
    netcat-openbsd \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype=/usr --with-jpeg=/usr \
 && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    intl \
    gd \
    zip \
    mbstring \
    opcache \
    sockets

# Install Redis extension (pinned to 5.x — redis 6.1+ changed hSet signature,
# which breaks Symfony's Redis6Proxy in symfony/cache 7.0)
RUN pecl install redis-5.3.7 \
 && docker-php-ext-enable redis

# Install AMQP extension
RUN pecl install amqp \
 && docker-php-ext-enable amqp

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html/app

# Copy application files
COPY . .

# Install Composer dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev || true

RUN chown -R www-data:www-data /var/www/html/app/var

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]