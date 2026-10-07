#!/bin/sh
# Telecharge validator-cli.jar et verifie son empreinte.
# Source unique de la version : appele par composer (post-install-cmd) et par le Dockerfile.
# Usage : bin/install-validator.sh [fichier cible, defaut bin/validator-cli.jar]
set -eu

VALIDATOR_VERSION=4.6.3
# sha256 de validator-cli.jar (a mettre a jour avec VALIDATOR_VERSION)
VALIDATOR_SHA256=09dc2b1447365ecf72ab6fed9a7f1e134314a7d0192865bae9e5cb861e87132f

TARGET="${1:-bin/validator-cli.jar}"

curl -fsSL -o "$TARGET" "https://github.com/IGNF/validator/releases/download/v${VALIDATOR_VERSION}/validator-cli.jar"
echo "${VALIDATOR_SHA256}  ${TARGET}" | sha256sum -c -
