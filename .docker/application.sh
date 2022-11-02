#!/bin/bash
set -e

ACTION=${1:-run}

export PGHOST=database
export PGUSER=$POSTGRES_USER
export PGPASSWORD=$POSTGRES_PASSWORD

#-------------------------------------------------------------------------------
# wait for postgresql...
#-------------------------------------------------------------------------------
until psql -l &> /dev/null;
do
  >&2 echo "PostgreSQL is unavailable - sleeping..."
  sleep 1
done

run(){
    #---------------------------------------------------------------------------
    # Ensure that database is created and schema is up to date.
    #---------------------------------------------------------------------------
    bin/console doctrine:database:create > /dev/null 2>&1 || true
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
    export DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@database:5432/validator_api_test?serverVersion=13&charset=utf8
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
