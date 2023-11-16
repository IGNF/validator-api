#----------------------------------------------------------------------
# Download vendor from composer.json in dedicated layer
#----------------------------------------------------------------------
FROM composer:2.3 as vendor

RUN mkdir -p /opt/validator-api
WORKDIR /opt/validator-api
COPY composer.json .
RUN composer install --no-scripts --prefer-dist --ignore-platform-req=ext-pcntl

#----------------------------------------------------------------------
# Create base layer for dev and production
#----------------------------------------------------------------------
FROM php:8.2-apache as base

ENV DEBIAN_FRONTEND=noninteractive
ENV LANG fr_FR.UTF-8
ENV VALIDATOR_PATH=/opt/ign-validator/validator-cli.jar

#----------------------------------------------------------------------
# Configure locale to fr_FR.UTF-8
# see also https://stackoverflow.com/a/41797247
#----------------------------------------------------------------------
RUN apt-get update && apt-get install -y locales \
 && sed -i -e 's/# en_US.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
 && dpkg-reconfigure locales \
 && update-locale LANG=fr_FR.UTF-8 \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Install common tools
#----------------------------------------------------------------------
RUN apt-get update \
  && apt-get install -y \
    unzip \
    make \
    curl wget \
    zip \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Configure PHP
#----------------------------------------------------------------------
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini

#----------------------------------------------------------------------
# Install PHP extensions
#----------------------------------------------------------------------
RUN apt-get update -qq \
 && apt-get install -y postgresql-client libpq-dev libzip-dev \
 && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
 && docker-php-ext-install opcache pdo pdo_pgsql pgsql zip pcntl \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Configure apache
#----------------------------------------------------------------------
COPY .docker/apache-ports.conf /etc/apache2/ports.conf
COPY .docker/apache-security.conf /etc/apache2/conf-enabled/security.conf
COPY .docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite remoteip

#----------------------------------------------------------------------
# Setup validator-cli.jar dependencies (java & ogr2ogr)
#----------------------------------------------------------------------
RUN apt-get update -qq \
 # see https://github.com/debuerreotype/docker-debian-artifacts/issues/24
 && mkdir -p /usr/share/man/man1 \
 && apt-get install -y openjdk-17-jdk-headless gdal-bin \
 && java -version \
 && ogrinfo --version \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Setup /opt/ign-validator/validator-cli.jar
#----------------------------------------------------------------------
ARG validator_version=4.4.0
RUN mkdir -p /opt/ign-validator \
 && wget --quiet -O ${VALIDATOR_PATH} https://github.com/IGNF/validator/releases/download/v${validator_version}/validator-cli.jar \
 && echo "validator-cli.jar version : $(java -jar /opt/ign-validator/validator-cli.jar version)"

#----------------------------------------------------------------------
# Install validator-api
#----------------------------------------------------------------------
COPY . /opt/validator-api
WORKDIR /opt/validator-api
COPY --from=vendor /opt/validator-api/vendor vendor

#----------------------------------------------------------------------
# Prepare data storage
# (Note that /opt/validator-api/var/data is shared between containers)
#----------------------------------------------------------------------
RUN mkdir -p /opt/validator-api/var/data/validations \
 && chown -R www-data:www-data /opt/validator-api/var

# ensure ogr2ogr can write in $HOME/.gdal ...
ENV HOME=/opt/validator-api/var

VOLUME /opt/validator-api/var/data

USER www-data

EXPOSE 8000
CMD ["/opt/validator-api/.docker/application.sh"]

#----------------------------------------------------------------------
# DEV image with xdebug
#----------------------------------------------------------------------
FROM base as dev

ENV APP_ENV=dev

USER root
# install xdebug extension for php
RUN pear config-set http_proxy ${http_proxy} \
 && pear config-set php_ini $PHP_INI_DIR/php.ini \
 && pecl install xdebug \
 && docker-php-ext-enable xdebug
USER www-data

#----------------------------------------------------------------------
# Production image without xdebug
#----------------------------------------------------------------------
FROM base as prod

ENV APP_ENV=prod
