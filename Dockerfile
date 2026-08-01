# Stage 1: Build Node.js assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: PHP Base Image
FROM php:8.2-fpm-alpine

# Install system dependencies, Nginx, and Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    $PHPIZE_DEPS

# Install PHP extensions required for Postgres and Redis
RUN docker-php-ext-install pdo pdo_pgsql pcntl bcmath zip
RUN pecl install redis && docker-php-ext-enable redis

# Clear out default nginx config and set up ours
RUN rm /etc/nginx/http.d/default.conf
COPY ./docker/prod/nginx.conf /etc/nginx/http.d/default.conf

# Configure Supervisor
COPY ./docker/prod/supervisord.conf /etc/supervisord.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
# Copy application code
COPY . .

# Copy compiled frontend assets from Stage 1
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies without dev tools
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set correct permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
