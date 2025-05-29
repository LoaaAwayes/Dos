
  # Dockerfile for Order Service 
FROM php:8.2.12-cli

# Install necessary dependencies
RUN apt-get update && apt-get install -y libsqlite3-dev && \
    docker-php-ext-install pdo pdo_sqlite

# Set working directory
WORKDIR /var/www

# Copy the application files into the container
COPY . .

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


# Expose the port the app runs on
EXPOSE 8008

# Command to run the app
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8008"] 

#php -S localhost:8002 -t public  
