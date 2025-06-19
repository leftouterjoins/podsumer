FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    ffmpeg \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /opt/podsumer

# Copy application files
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /opt/podsumer \
    && chmod -R 755 /opt/podsumer \
    && chmod +x /opt/podsumer/scripts/refresh_feeds.php

# Create media directory
RUN mkdir -p /opt/media && chown -R www-data:www-data /opt/media

# Configure Apache - fix the path to use the correct apache.conf file
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Create a script to generate the crontab with the configured interval
RUN echo '#!/bin/bash\n\
REFRESH_INTERVAL=$(php -r "include \"/opt/podsumer/conf/podsumer.conf\"; echo \$feed_refresh_interval ?? 6;")\n\
echo "0 */${REFRESH_INTERVAL} * * * www-data /usr/local/bin/php /opt/podsumer/scripts/refresh_feeds.php >> /var/log/cron.log 2>&1" > /etc/cron.d/podsumer-cron\n\
chmod 0644 /etc/cron.d/podsumer-cron\n\
crontab /etc/cron.d/podsumer-cron\n\
service cron start\n\
apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Start the container with our custom script
CMD ["/usr/local/bin/start.sh"]

