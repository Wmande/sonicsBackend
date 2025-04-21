# Use official PHP image with PHP 8.2
FROM php:8.2-fpm

# Install system dependencies, including Nginx
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy composer files first to cache dependencies
COPY composer.json composer.lock ./
RUN php -d memory_limit=-1 /usr/local/bin/composer install --optimize-autoloader --no-dev

# Copy application files
COPY . .

# Copy Nginx configuration
COPY ./nginx.conf /etc/nginx/sites-available/default

# Copy start script
COPY ./start.sh /start.sh
RUN chmod +x /start.sh

# Set permissions for Laravel directories
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public
RUN chmod -R 755 /var/www/storage /var/www/bootstrap/cache /var/www/public

# Expose port 80 for HTTP traffic
EXPOSE 80

# Start Nginx and PHP-FPM using the start script
CMD ["/start.sh"]
