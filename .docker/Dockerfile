#----------------------------------------------------------------------
# Download vendor from composer.json in dedicated layer
#----------------------------------------------------------------------
FROM composer:latest AS vendor

RUN mkdir -p /opt/validator-api
WORKDIR /opt/validator-api
COPY composer.json .
RUN composer install --no-scripts --prefer-dist --ignore-platform-req=ext-pcntl

#----------------------------------------------------------------------
# Create base layer for dev and production
#----------------------------------------------------------------------
FROM php:8.3-apache AS base

ENV DEBIAN_FRONTEND=noninteractive
ENV LANG=fr_FR.UTF-8
ENV VALIDATOR_PATH=/opt/ign-validator/validator-cli.jar

#----------------------------------------------------------------------
# Configure locale to fr_FR.UTF-8
# see also https://stackoverflow.com/a/41797247
#----------------------------------------------------------------------
RUN apt-get update && apt-get install --no-install-recommends -y locales \
 && sed -i -e 's/# en_US.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
 && dpkg-reconfigure locales \
 && update-locale LANG=fr_FR.UTF-8 \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Install common tools
#----------------------------------------------------------------------
RUN apt-get update \
  && apt-get install --no-install-recommends -y \
    unzip zip \
    curl \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Configure PHP
#----------------------------------------------------------------------
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini

#----------------------------------------------------------------------
# Install PHP extensions
#----------------------------------------------------------------------
RUN apt-get update -qq \
 && apt-get install --no-install-recommends -y postgresql-client libpq-dev libzip-dev \
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
 && apt-get install --no-install-recommends -y openjdk-17-jdk-headless gdal-bin \
 && java -version \
 && ogrinfo --version \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Setup /opt/ign-validator/validator-cli.jar
#----------------------------------------------------------------------
ARG validator_version=4.4.5
RUN mkdir -p /opt/ign-validator \
 && curl -sS -o ${VALIDATOR_PATH} https://github.com/IGNF/validator/releases/download/v${validator_version}/validator-cli.jar \
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

ENV APP_ENV=prod

EXPOSE 8000
CMD ["/opt/validator-api/.docker/application.sh"]

