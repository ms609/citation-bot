# If you make any changes to this file, don't forget to rebuild the Docker image using the --build flag:
# docker-compose up --build -d

FROM php:8.4-apache

# Install PHP XDebug, for step debugging and for PHPUnit code coverage report.
# You can leave port 9007 for all your Docker containers. It doesn't conflict across containers like the localhost port does.
# Add this .vscode/launch.json file to your repo, then go to Run and Debug -> press play:
# {
# 	// Use IntelliSense to learn about possible attributes.
# 	// Hover to view descriptions of existing attributes.
# 	// For more information, visit: https://go.microsoft.com/fwlink/?linkid=830387
# 	"version": "0.2.0",
# 	"configurations": [
# 		{
# 			"name": "Listen for Xdebug",
# 			"type": "php",
# 			"request": "launch",
# 			"port": 9007,
# 			"pathMappings": {
#				"/var/www/html/": "${workspaceRoot}"
# 			}
# 		}
# 	]
# }
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
RUN echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9007" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Needed for PHPUnit time limit options (e.g. --enforce-time-limit --default-time-limit 13000)
RUN docker-php-ext-install pcntl

# Install composer. Once the container is built and running, you can do `composer update` with the following shell command: `docker exec -it citation-bot-php-1 composer update`
RUN apt-get update && apt-get install --no-install-recommends -y git zip unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Allow directory listings. Not a security issue since this is a test environment. Makes it easier to navigate.
RUN a2enmod autoindex
RUN echo "<Directory /var/www/html>\n    Options +Indexes\n    AllowOverride All\n</Directory>" > /etc/apache2/conf-available/directory-listing.conf \
    && a2enconf directory-listing

# If ever deployed into production instead of just for testing, then two things need done:
# 1.  Do not run the webserver as root - would need to change ownership of a bunch of files, create user, run apache as user, etc.
# 2.  Add a HEALTHCHECK to the container
# These are both set to be ignored in trivy-analysis.yml
