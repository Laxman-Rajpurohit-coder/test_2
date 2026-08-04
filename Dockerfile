# Use PHP 8.2 FPM Alpine or Debian. Debian is easier for FFmpeg and Supervisor.
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    ffmpeg \
    libpq-dev \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip pcntl sockets

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy custom configurations
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Rename .env.railway to .env so the build can proceed with default variables
RUN cp .env.railway .env

# Provide dummy ENV variables for the build phase to prevent package:discover crashes
ENV RAILWAY_STATIC_URL="localhost"

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Generate APP_KEY
RUN php artisan key:generate --force

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Create storage symlink
RUN php artisan storage:link

# Expose HTTP port (Railway automatically routes traffic to this port)
EXPOSE 80

# Start Supervisor to manage Nginx, PHP-FPM, Reverb, and Queue Worker
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
