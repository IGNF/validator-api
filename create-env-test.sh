#!/bin/bash

set -e

touch .env.test

echo "APP_ENV=test" >.env.test
echo "DATABASE_URL=${DATABASE_URL}" >>.env.test
echo "POSTGRES_USER=${POSTGRES_USER}" >>.env.test
echo "POSTGRES_PASSWORD=${POSTGRES_PASSWORD}" >>.env.test
echo "POSTGRES_DB=${POSTGRES_DB}" >>.env.test

cat .env.test
