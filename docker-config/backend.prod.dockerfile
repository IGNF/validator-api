### backend.prod.dockerfile

# Base image
FROM php:7.3-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

# Common tools
RUN apt-get update -qq && \
    apt-get install -qy \
    git \
    gnupg \
    unzip \
    make \
    zip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# PHP configuration & extensions
RUN apt-get update
COPY docker-config/php.ini /usr/local/etc/php/conf.d/app.ini

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Apache configuration
COPY docker-config/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker-config/apache.conf /etc/apache2/conf-available/z-app.conf

RUN a2enmod rewrite remoteip && \
    a2enconf z-app

# Java 11
RUN mkdir /usr/share/man/man1/ && \
    apt install -y default-jre
RUN echo 'export JAVA_HOME=$(dirname $(dirname $(readlink -f $(which java))))' >> ~/.bashrc && \
    echo 'export PATH=$PATH:$JAVA_HOME/bin' >> ~/.bashrc && \
    java -version

# (Re)-build app
COPY . .
RUN make compile-app-prod