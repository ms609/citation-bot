# If you make any changes to this file, don't forget to rebuild the Docker image using the --build flag:
# docker-compose up --build -d

FROM php:8.5-apache@sha256:ae83253bb4d8b8b9715e0846ac915dae15d19818947f0a27cbcf88f4711a76fa

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
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9007" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Apply OS security patches, install system packages, PHP extensions, and composer in one layer
# to minimize layer count where OS package vulnerabilities (e.g. perl) are reported
RUN apt-get update && apt-get upgrade -y \
    && apt-get install --no-install-recommends -y \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        git \
        zip \
        unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install curl mbstring xml pcntl \
    && curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && EXPECTED_SIGNATURE=$(curl -sS https://composer.github.io/installer.sig) \
    && ACTUAL_SIGNATURE=$(php -r "echo hash_file('sha384', 'composer-setup.php');") \
    && if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then echo 'ERROR: Invalid installer signature' >&2; rm composer-setup.php; exit 1; fi \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Allow directory listings. Not a security issue since this is a test environment.
RUN a2enmod autoindex \
    && echo "<Directory /var/www/html>\n    Options +Indexes\n    AllowOverride All\n    <FilesMatch \"^(env\\.php|DebugLog\\.txt|cookie\\.txt)$\">\n        Require all denied\n    </FilesMatch>\n</Directory>" > /etc/apache2/conf-available/directory-listing.conf \
    && a2enconf directory-listing

# If ever deployed into production instead of just for testing, then two things need done:
# 1.  Do not run the webserver as root - would need to change ownership of a bunch of files, create user, run apache as user, etc.
# 2.  Add a HEALTHCHECK to the container
# These are both set to be ignored in trivy-analysis.yml
