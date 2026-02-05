FROM php:8.4-fpm

# Install dependensi sistem, PHP extensions, dan libicu untuk intl
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql gd zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy kode aplikasi
COPY . .

# Install dependensi Laravel (Tambahkan ignore-platform-reqs jika masih ada konflik kecil)
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Atur izin folder storage dan cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]