#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

VALIDATOR_VERSION=${VALIDATOR_VERSION:-4.4.8}
URL="https://github.com/IGNF/validator/releases/download/v$VALIDATOR_VERSION/validator-cli.jar"

echo "Downloading validator v${VALIDATOR_VERSION} from ${URL}..."
wget -q -O "${SCRIPT_DIR}/bin/validator-cli.jar" "$URL"
