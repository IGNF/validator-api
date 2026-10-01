ARG REGISTRY=docker.io
# images versions
ARG UBUNTU_IMAGE_VERSION=24.04
ARG COMPOSER_IMAGE_VERSION=2.10
# packages versions
ARG OPENJDK_VERSION=17
ARG PHP_VERSION=8.5
# composer no dev
ARG COMPOSER_NO_DEV=1

#----------------------------------------------------------------------
# Composer stage - PHP dependencies -------------------------------------
#----------------------------------------------------------------------
FROM ${REGISTRY}/library/composer:${COMPOSER_IMAGE_VERSION} AS composer

ARG COMPOSER_NO_DEV

WORKDIR /opt/validator-api
COPY composer.json composer.lock ./

# add --no-dev if COMPOSER_NO_DEV is 1 (for production)
RUN composer install $(if [ "${COMPOSER_NO_DEV}" = "1" ]; then echo --no-dev; fi) \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --ignore-platform-req=ext-pcntl \
  && composer clear-cache

#----------------------------------------------------------------------
# Production application container ---------------------------------------
#----------------------------------------------------------------------
FROM ${REGISTRY}/library/ubuntu:${UBUNTU_IMAGE_VERSION}

# Redeclare build args for this stage
ARG PHP_VERSION
ARG OPENJDK_VERSION

# Metadata labels
LABEL description="validator-api - APIsation of Validator, a tool that allows to validate and normalize datasets according to a file mapping and a FeatureCatalog." \
      org.opencontainers.image.source="https://github.com/IGNF/validator-api"

# Environment variables
ENV DEBIAN_FRONTEND=noninteractive \
    LANG=fr_FR.UTF-8 LC_ALL=fr_FR.UTF-8 LANGUAGE=fr_FR.UTF-8 \
    TZ=Europe/Paris \
    VALIDATOR_PATH=/opt/ign-validator/validator-cli.jar

# System setup: packages, locale (https://stackoverflow.com/a/41797247), trust store
RUN apt-get update -qq \
  && apt-get install --no-install-recommends -y \
    locales \
    ca-certificates \
  && echo "fr_FR.UTF-8 UTF-8" > /etc/locale.gen \
  && locale-gen fr_FR.UTF-8 \
  && update-locale LANG=fr_FR.UTF-8 LC_ALL=fr_FR.UTF-8 \
  && update-ca-certificates \
  && rm -rf /var/lib/apt/lists/*

#------------------------------------------------------------------------
# Configure https://packages.sury.org/php/ to get latest PHP versions
#------------------------------------------------------------------------
RUN apt-get update -qq \
  && apt-get install --no-install-recommends -y gnupg2 software-properties-common \
  && add-apt-repository -y ppa:ondrej/php \
  && apt-get remove -y software-properties-common \
  && rm -rf /var/lib/apt/lists/*

# Install required packages
RUN apt-get update -qq \
  # see https://github.com/debuerreotype/docker-debian-artifacts/issues/24
  # (openjdk install fails on minimal images without this directory)
  && mkdir -p /usr/share/man/man1 \
  && apt-get install --no-install-recommends -y \
    # System utilities
    unzip zip file \
    # Database client
    postgresql-client \
    # Apache2
    apache2 libapache2-mod-php${PHP_VERSION} \
    # PHP & extensions
    php${PHP_VERSION} \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-mbstring \
    # (no php-opcache package: OPcache is built into PHP since 8.5)
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-pdo \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-curl \
    # java & ogr2ogr, required by validator-cli.jar
    openjdk-${OPENJDK_VERSION}-jre-headless gdal-bin \
  && java -version \
  && ogrinfo --version \
  && apt-get upgrade -y --no-install-recommends \
  && apt-get autoremove -y \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

#----------------------------------------------------------------------
# Apache and PHP configuration -------------------------------------------
#----------------------------------------------------------------------

# Add helper script to start apache (https://github.com/docker-library/php)
COPY .docker/apache2-foreground /usr/local/bin/apache2-foreground
# Copy php configuration files
COPY .docker/php.ini /etc/php/${PHP_VERSION}/apache2/conf.d/99-app.ini
COPY .docker/php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-app.ini
# Copy apache configuration files
COPY .docker/apache-ports.conf /etc/apache2/ports.conf
COPY .docker/apache-security.conf /etc/apache2/conf-available/security.conf
COPY .docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
# Set permissions for helper script and apache configuration files
RUN chmod +x /usr/local/bin/apache2-foreground \
  # Create apache2 repository (https://github.com/docker-library/php)
  && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
  && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
  # Redirects logs to stdout / stderr (https://github.com/docker-library/php)
  && ln -sfT /dev/stderr /var/log/apache2/error.log \
  && ln -sfT /dev/stdout /var/log/apache2/access.log \
  && ln -sfT /dev/stdout /var/log/apache2/other_vhosts_access.log \
  # Enable Apache modules and configurations
  && a2enconf security \
  && a2enmod rewrite remoteip

#----------------------------------------------------------------------
# Setup /opt/ign-validator/validator-cli.jar
# (version and sha256 are defined in bin/install-validator.sh)
#----------------------------------------------------------------------
COPY bin/install-validator.sh /tmp/install-validator.sh
RUN apt-get update -qq \
  && apt-get install --no-install-recommends -y curl \
  && mkdir -p /opt/ign-validator \
  && sh /tmp/install-validator.sh ${VALIDATOR_PATH} \
  && echo "validator-cli.jar version : $(java -jar ${VALIDATOR_PATH} version)" \
  && rm -rf /tmp/install-validator.sh /tmp/hsperfdata_root \
  && apt-get purge -y curl \
  && apt-get autoremove -y \
  # Echec du build si curl reste present dans l'image
  && ! [ -e /usr/bin/curl ] \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* \
  # Remove unused Canonical Pebble bundled in the Ubuntu base image
  # (vulnerable Go stdlib, not used: container runs via bin/application.sh)
  && rm -rf /usr/bin/pebble /var/lib/pebble

#----------------------------------------------------------------------
# Application setup
#----------------------------------------------------------------------
COPY . /opt/validator-api
WORKDIR /opt/validator-api
COPY --from=composer --chown=www-data:www-data /opt/validator-api/vendor ./vendor

#----------------------------------------------------------------------
# Prepare data storage
# (Note that /opt/validator-api/var/data is shared between containers)
#----------------------------------------------------------------------
RUN mkdir -p var/data/validations \
  && chown -R www-data:www-data var \
  && chmod -R +x bin

# ensure ogr2ogr can write in $HOME/.gdal ...
ENV HOME=/opt/validator-api/var

# Define volume for persistent data
VOLUME /opt/validator-api/var/data

#----------------------------------------------------------------------
# Security and final configuration ---------------------------------------
#----------------------------------------------------------------------

# Security: Run as non-root user
USER www-data

ENV APP_ENV=prod

EXPOSE 8000

# Use exec form for better signal handling
CMD ["/opt/validator-api/bin/application.sh"]
