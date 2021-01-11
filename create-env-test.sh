#!/bin/bash

# only for travis ci
set -e

touch .env.test

echo "APP_ENV=test" >.env.test
echo "DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@${POSTGRES_HOST}:${POSTGRES_PORT}/${POSTGRES_DB}?serverVersion=10&charset=utf8" >>.env.test
echo "POSTGRES_USER=${POSTGRES_USER}" >>.env.test
echo "POSTGRES_PASSWORD=${POSTGRES_PASSWORD}" >>.env.test
echo "POSTGRES_DB=${POSTGRES_DB}" >>.env.test

cat .env.test
