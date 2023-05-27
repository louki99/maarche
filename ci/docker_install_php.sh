#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

apt-get install -y libpq-dev libxml2-dev libxslt1-dev libpng-dev unoconv xpdf-utils imagemagick ghostscript wkhtmltopdf libzip-dev zip \
&& docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
&& docker-php-ext-configure pdo_pgsql --with-pdo-pgsql \
&& docker-php-ext-install pdo_pgsql pgsql \
&& docker-php-ext-install xsl soap zip \
&& pecl install xdebug-3.1.2 \
&& docker-php-ext-enable xdebug \
&& docker-php-ext-install gd \
&& FIREFOX_URL="https://download.mozilla.org/?product=firefox-latest-ssl&os=linux64&lang=en-US" \
&& ACTUAL_URL=$(curl -Ls -o /dev/null -w %{url_effective} $FIREFOX_URL) \
&& curl --silent --show-error --location --fail --retry 3 --output /tmp/firefox.tar.bz2 $ACTUAL_URL \
&& tar -xvjf /tmp/firefox.tar.bz2 -C /opt \
&& ln -s /opt/firefox/firefox /usr/local/bin/firefox \
&& curl --silent --show-error --location --fail --retry 3 --output /tmp/google-chrome-stable_current_amd64.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb \
&& (dpkg -i /tmp/google-chrome-stable_current_amd64.deb || apt-get -fy install)  \
&& rm -rf /tmp/google-chrome-stable_current_amd64.deb \
&& sed -i 's|HERE/chrome"|HERE/chrome" --disable-setuid-sandbox --no-sandbox|g' "/opt/google/chrome/google-chrome" \
&& a2enmod rewrite \
&& touch directory.txt \
&& echo "<Directory /var/www/html>" >> directory.txt \
&& echo "Options Indexes FollowSymLinks" >> directory.txt \
&& echo "AllowOverride All" >> directory.txt \
&& echo "Require all granted" >> directory.txt \
&& echo "SetEnv MAARCH_ENCRYPT_KEY \"Security Key Maarch Courrier CI\"" >> directory.txt \
&& echo "</Directory>" >> directory.txt \
&& sed -i -e '/CustomLog/r directory.txt' /etc/apache2/sites-available/000-default.conf \
&& cp ci/php.ini /usr/local/etc/php/conf.d/php.ini
