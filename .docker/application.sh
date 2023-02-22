#!/bin/bash
set -e

ACTION=${1:-run}

run(){
    #---------------------------------------------------------------------------
    # Ensure that database is created and schema is up to date.
    #---------------------------------------------------------------------------
    bin/console doctrine:database:create --if-not-exists
    bin/console doctrine:schema:update --force

    #---------------------------------------------------------------------------
    # start apache as www-data
    #---------------------------------------------------------------------------
    /usr/sbin/apachectl -D FOREGROUND
}

backend(){
    bash loop-validate.sh
}

test(){
    export APP_ENV=test
    export DATABASE_URL="postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@database:5432/validator_api_test?serverVersion=13&charset=utf8"
    bin/console --env=test doctrine:database:create --if-not-exists
    bin/console --env=test doctrine:schema:update --force
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
