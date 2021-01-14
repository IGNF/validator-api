### backend.dockerfile

# Base image
FROM php:7.3-apache

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN rm /etc/apt/preferences.d/no-debian-php

# Common tools
RUN apt-get update -qq && \
    apt-get install -qy \
    git \
    gnupg \
    unzip \
    make \
    php-dev \
    zip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
    apt-get update && apt-get install -y yarn && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP Configuration & Extensions
RUN apt-get update
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Needed for pecl to succeed
RUN pear config-set php_ini /usr/local/etc/php/conf.d/app.ini
RUN if [ "${http_proxy}" != "" ]; then \
    pear config-set http_proxy ${http_proxy} \
    ;fi
RUN pecl install xdebug-2.8.1 \
    && docker-php-ext-enable xdebug

RUN apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip

# Apache Configuration
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/apache.conf /etc/apache2/conf-available/z-app.conf

RUN a2enmod rewrite remoteip && \
    a2enconf z-app

# Java 11
RUN mkdir /usr/share/man/man1/ && \
    apt-get install -y default-jre
RUN echo 'export JAVA_HOME=$(dirname $(dirname $(readlink -f $(which java))))' >> ~/.bashrc && \
    echo 'export PATH=$PATH:$JAVA_HOME/bin' >> ~/.bashrc && \
    java -version

# GDAL
RUN apt-get install -y gdal-bin && \
    ogrinfo --version


RUN chown -R www-data .
RUN chmod 777 -R .