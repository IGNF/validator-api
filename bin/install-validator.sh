#!/bin/sh
# Telecharge validator-cli.jar et verifie son empreinte.
# Source unique de la version : appele par composer (post-install-cmd) et par le Dockerfile.
# Usage : bin/install-validator.sh [fichier cible, defaut bin/validator-cli.jar]
set -eu

VALIDATOR_VERSION=4.6.1
# sha256 de validator-cli.jar (a mettre a jour avec VALIDATOR_VERSION)
VALIDATOR_SHA256=sha256:bc1b8b9b7bf4b7423b8fffa1960adc52117d19c7be30f7596699925657da5ea1

TARGET="${1:-bin/validator-cli.jar}"

curl -fsSL -o "$TARGET" "https://github.com/IGNF/validator/releases/download/v${VALIDATOR_VERSION}/validator-cli.jar"
echo "${VALIDATOR_SHA256}  ${TARGET}" | sha256sum -c -
