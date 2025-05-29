FROM php:8.2.12-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install dependencies
RUN composer install

# Create SQLite database directory
RUN mkdir -p /var/www/database/db

# Set permissions
RUN chmod -R 777 /var/www/storage /var/www/bootstrap/cache /var/www/database/db

# Expose port
EXPOSE 9000

# Startup command
CMD php artisan serve --host=0.0.0.0 --port=9000
