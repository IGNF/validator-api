#!/bin/sh
# Telecharge validator-cli.jar et verifie son empreinte.
# Source unique de la version : appele par composer (post-install-cmd) et par le Dockerfile.
# Usage : bin/install-validator.sh [fichier cible, defaut bin/validator-cli.jar]
set -eu

VALIDATOR_VERSION=4.5.6
# sha256 de validator-cli.jar (a mettre a jour avec VALIDATOR_VERSION)
VALIDATOR_SHA256=2c773342afd4a9107a1cc9e6176933dbb246cf473cd8f92811d296b4e28805fa

TARGET="${1:-bin/validator-cli.jar}"

curl -fsSL -o "$TARGET" "https://github.com/IGNF/validator/releases/download/v${VALIDATOR_VERSION}/validator-cli.jar"
echo "${VALIDATOR_SHA256}  ${TARGET}" | sha256sum -c -
