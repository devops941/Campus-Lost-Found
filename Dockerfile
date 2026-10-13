FROM php:8.2-apache

# Install system dependencies & PDO MySQL
RUN apt-get update && apt-get install -y git unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Change Apache default port from 80 to 10000 for Render
RUN sed -i 's/80/10000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set working directory and copy application files
WORKDIR /var/www/html
COPY . /var/www/html/

# Install PHP dependencies if composer.json is present
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Create required directories and set proper write permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/logs

EXPOSE 10000

CMD ["apache2-foreground"]
