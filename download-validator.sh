#!/bin/bash

set -e

VALIDATOR_VERSION=$1

if [ -z ${VALIDATOR_VERSION+x} ]; then
    echo "VALIDATOR_VERSION is unset";
    exit 1
elif [ -z "$VALIDATOR_VERSION" ]; then
    echo "VALIDATOR_VERSION is blank";
    exit 2
else
    echo "Downloading validator v$VALIDATOR_VERSION";
fi

URL="https://github.com/IGNF/validator/releases/download/v$VALIDATOR_VERSION/validator-cli.jar"
echo $URL

curl --location $URL > ./validator-cli.jar