FROM php:8.2-cli

# Install required dependencies
RUN apt-get update && apt-get install -y unzip libzip-dev sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo pdo_sqlite

# Install Composer
#COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy source code
COPY . .

# Make sure required folders exist before setting permissions
RUN mkdir -p storage bootstrap/cache database/db

# Set permissions (only if directories exist)
RUN chmod -R 777 storage bootstrap/cache database/db || true

# Install PHP dependencies
#RUN composer install || true

# Expose port and start server
EXPOSE 9000
CMD ["php", "-S", "0.0.0.0:9000", "-t"]
