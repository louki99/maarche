#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

apt-get install -y libpq-dev libxml2-dev libxslt1-dev libpng-dev unoconv xpdf-utils imagemagick ghostscript wkhtmltopdf libzip-dev zip \
&& docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
&& docker-php-ext-configure pdo_pgsql --with-pdo-pgsql \
&& docker-php-ext-install pdo_pgsql pgsql \
&& docker-php-ext-install xsl soap zip \
&& docker-php-ext-install gd

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
mv composer.phar /usr/local/bin/composer

curl -sL https://deb.nodesource.com/setup_18.x | bash - > /dev/null
apt-get install -y nodejs
npm install -g n
n 18
node -v
npm -v