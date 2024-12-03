#!/bin/bash
set -e

#---------------------------------------------------------------------------
# env vars specific to .docker/application.sh
#---------------------------------------------------------------------------

# allows to enable / disable automatic database creation (doctrine:database:create)
DB_CREATE=${DB_CREATE:-0}
# allows to enable / disable automatic schema upgrade (doctrine:schema:update)
DB_UPGRADE=${DB_UPGRADE:-1}

# run (apache2) / backend / test 
ACTION=${1:-run}

run(){
    #---------------------------------------------------------------------------
    # Ensure that database is created and schema is up to date.
    #---------------------------------------------------------------------------
    if [ "$DB_CREATE" = "1" ];
    then
        bin/console doctrine:database:create --if-not-exists
    fi
    if [ "$DB_UPGRADE" = "1" ];
    then
        bin/console doctrine:schema:update --force --complete
    fi

    # Ensure apache2 can read /etc/apache2/envvars
    # if not we got the following error
    # apache2: Syntax error on line 80 of /etc/apache2/apache2.conf: DefaultRuntimeDir must be a valid directory, absolute or relative to ServerRoot
    mkdir -p /var/run/apache2
    source /etc/apache2/envvars

    #---------------------------------------------------------------------------
    # start apache2 server
    #---------------------------------------------------------------------------
    exec apache2-foreground
}

backend(){
    exec bash loop-validate.sh
}

test(){
    export APP_ENV=test
    export DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@database:5432/validator_api_test?serverVersion=15&charset=utf8"
    bin/console --env=test doctrine:database:create --if-not-exists
    bin/console --env=test doctrine:schema:update --complete --force
    XDEBUG_MODE=coverage vendor/bin/phpunit
}

if [ $ACTION = "run" ]; then
    run;
elif [ $ACTION = "backend" ]; then
    backend;
elif [ $ACTION = "test" ]; then
    test;
else
    echo "undefined action $ACTION"
fi
