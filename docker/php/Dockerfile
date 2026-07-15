# ===========================================
# PHP-FPM Dockerfile for Laravel 12
# ===========================================
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-alpine

LABEL maintainer="SMS Enterprise"

# ===========================================
# Install Dependencies
# ===========================================
RUN apk add --no-cache \
    # Build dependencies
    $PHPIZE_DEPS \
    # Common utilities
    bash \
    curl \
    git \
    unzip \
    zip \
    # PostgreSQL
    postgresql-dev \
    # Image processing
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    # Internationalization
    icu-dev \
    # XML
    libxml2-dev \
    # Zip
    libzip-dev \
    # Linux headers for sockets
    linux-headers

# ===========================================
# Install PHP Extensions
# ===========================================
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    gd \
    intl \
    opcache \
    pcntl \
    bcmath \
    zip \
    sockets \
    exif

# ===========================================
# Install Redis Extension
# ===========================================
RUN pecl install redis \
    && docker-php-ext-enable redis

# ===========================================
# Install Composer
# ===========================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ===========================================
# Configure PHP
# ===========================================
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ===========================================
# Create Application User
# ===========================================
RUN addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www

# ===========================================
# Set Working Directory
# ===========================================
WORKDIR /var/www/html

# ===========================================
# Set Permissions
# ===========================================
RUN chown -R www:www /var/www/html

# ===========================================
# Switch to Non-Root User
# ===========================================
USER www

# ===========================================
# Expose Port
# ===========================================
EXPOSE 9000

CMD ["php-fpm"]
