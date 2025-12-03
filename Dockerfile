# If you make any changes to this file, don't forget to rebuild the Docker image using the --build flag:
# docker-compose up --build -d

FROM php:8.4-apache

# Install composer. Once the container is built and running, you can do `composer update` with the following shell command: `docker exec -it citation-bot-php-1 composer update`
RUN apt-get update && apt-get install -y git zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Allow directory listings. Not a security issue since this is a test environment. Makes it easier to navigate.
RUN a2enmod autoindex
RUN echo "<Directory /var/www/html>\n    Options +Indexes\n    AllowOverride All\n</Directory>" > /etc/apache2/conf-available/directory-listing.conf \
    && a2enconf directory-listing
