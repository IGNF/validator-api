FROM php:8.2-apache

#ENV COMPOSER_ALLOW_SUPERUSER=1
RUN rm /etc/apt/preferences.d/no-debian-php

#----------------------------------------------------------------------
# Env vars for validator-api
#----------------------------------------------------------------------
ENV APP_ENV=prod
ENV VALIDATOR_PATH=/opt/ign-validator/validator-cli.jar

ENV POSTGRES_USER=validator
ENV POSTGRES_PASSWORD=validator

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y locales

#----------------------------------------------------------------------
# Configure locale to fr_FR.UTF-8
# see also https://stackoverflow.com/a/41797247
#----------------------------------------------------------------------
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y locales \
 && sed -i -e 's/# en_US.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen \
 && dpkg-reconfigure --frontend=noninteractive locales \
 && update-locale LANG=fr_FR.UTF-8 \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ENV LANG fr_FR.UTF-8

#----------------------------------------------------------------------
# Install common tools
#----------------------------------------------------------------------
RUN apt-get update -qq \
  && apt-get install -y lsb-release gnupg2 wget curl vim git \
  && echo "deb https://packages.sury.org/php/ $(lsb_release -cs) main" > /etc/apt/sources.list.d/php.list \
  && curl -sS https://packages.sury.org/php/apt.gpg | apt-key add - \
  && apt-get update -qq \
  && apt-get install -qy \
    unzip \
    make \
    php-dev \
    zip \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

#----------------------------------------------------------------------
# Configure PHP and pecl
#----------------------------------------------------------------------
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini
RUN pear config-set php_ini /usr/local/etc/php/conf.d/app.ini
RUN if [ "${http_proxy}" != "" ]; then \
    pear config-set http_proxy ${http_proxy} \
    ;fi

#----------------------------------------------------------------------
# Install PHP extensions
#----------------------------------------------------------------------
RUN apt-get update -qq \
 && apt-get install -y postgresql-client libpq-dev libzip-dev \
 && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
 && docker-php-ext-install pdo pdo_pgsql pgsql zip \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

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
 && apt-get install -y openjdk-11-jdk-headless gdal-bin \
 && java -version \
 && ogrinfo --version \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

#----------------------------------------------------------------------
# Setup /opt/ign-validator/validator-cli.jar
#----------------------------------------------------------------------
ARG validator_version=4.4.0
RUN apt-get update -qq \
 && wget --quiet -O /tmp/validator-cli.deb https://github.com/IGNF/validator/releases/download/v${validator_version}/ign-validator_${validator_version}_all.deb \
 && dpkg -i /tmp/validator-cli.deb \
 && rm -f /tmp/validator-cli.deb \
 && echo "validator-cli.jar version : $(java -jar /opt/ign-validator/validator-cli.jar version)" \
 && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

#----------------------------------------------------------------------
# Install validator-api
#----------------------------------------------------------------------
COPY --chown=www-data:www-data . /opt/validator-api
WORKDIR /opt/validator-api
USER www-data
RUN composer install

#----------------------------------------------------------------------
# Prepare data storage
# (Note that /opt/validator-api/var/data is shared between containers)
#----------------------------------------------------------------------
RUN mkdir -p /opt/validator-api/var/data/validations
VOLUME /opt/validator-api/var/data


#----------------------------------------------------------------------
# Replace default command
#----------------------------------------------------------------------
EXPOSE 8000
CMD ["/opt/validator-api/.docker/application.sh"]
