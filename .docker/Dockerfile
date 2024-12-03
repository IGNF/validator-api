#----------------------------------------------------------------------
# Download vendor from composer.json in dedicated layer
#----------------------------------------------------------------------
ARG registry=docker.io
FROM ${registry}/library/composer:latest AS vendor

RUN mkdir -p /opt/validator-api
WORKDIR /opt/validator-api
COPY composer.json .
RUN composer install --no-scripts --prefer-dist --ignore-platform-req=ext-pcntl

#----------------------------------------------------------------------
# Create application container
#----------------------------------------------------------------------
FROM ${registry}/library/ubuntu:24.04

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
    curl wget \
    file \
    postgresql-client \
 && rm -rf /var/lib/apt/lists/*


#------------------------------------------------------------------------
# Configure https://packages.sury.org/php/ to get latests PHP versions
#------------------------------------------------------------------------
RUN apt-get update \
 && apt-get install --no-install-recommends -y gnupg2 software-properties-common \
 && add-apt-repository -y ppa:ondrej/php \
 && apt-get remove -y software-properties-common \
 && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Install Apache, PHP and its extensions
# TODO : php8.3-pcntl ?
#----------------------------------------------------------------------
RUN apt-get update \
  && apt-get install --no-install-recommends -y \
      apache2 php8.3 libapache2-mod-php8.3 \
      php8.3-opcache php8.3-xml \
      php8.3-pdo php8.3-pgsql php8.3-zip \
      php8.3-curl \
  && rm -rf /var/lib/apt/lists/*

#------------------------------------------------------------------------
# Add helper script to start apache
# (see https://github.com/docker-library/php)
#------------------------------------------------------------------------
COPY .docker/apache2-foreground /usr/local/bin/apache2-foreground
RUN chmod +x /usr/local/bin/apache2-foreground

#------------------------------------------------------------------------
# Create apache2 repository
# (see https://github.com/docker-library/php)
#------------------------------------------------------------------------
RUN mkdir -p /var/run/apache2 && chown -R www-data:www-data /var/run/apache2 \
 && mkdir -p /var/lock/apache2 && chown -R www-data:www-data /var/lock/apache2 \
 && mkdir -p /var/log/apache2 && chown -R www-data:www-data /var/log/apache2

#------------------------------------------------------------------------
# Redirects logs to stdout / stderr
# (see https://github.com/docker-library/php)
#------------------------------------------------------------------------
RUN ln -sfT /dev/stderr "/var/log/apache2/error.log" \
 && ln -sfT /dev/stdout "/var/log/apache2/access.log" \
 && ln -sfT /dev/stdout "/var/log/apache2/other_vhosts_access.log" \
 && chown www-data:www-data /var/log/apache2/*.log

#----------------------------------------------------------------------
# Configure PHP
#----------------------------------------------------------------------
COPY .docker/php.ini /etc/php/8.3/apache2/conf.d/99-app.ini
COPY .docker/php.ini /etc/php/8.3/cli/conf.d/99-app.ini

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

ENV APP_ENV=prod

EXPOSE 8000
CMD ["/opt/validator-api/.docker/application.sh"]
