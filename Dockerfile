FROM php:8.2-fpm

# Install essential system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && chmod +x /usr/local/lib/php/extensions/*/xdebug.so \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /tmp/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (include dev dependencies for local development)
RUN composer install --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache


# Copy Xdebug configuration
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Copy and set executable permission for startup script
COPY start.sh /var/www/html/start.sh
RUN chmod +x /var/www/html/start.sh

EXPOSE 8000

CMD ["/var/www/html/start.sh"]