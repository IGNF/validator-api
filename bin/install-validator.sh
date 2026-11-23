#!/bin/sh
# Telecharge validator-cli.jar et verifie son empreinte.
# Source unique de la version : appele par composer (post-install-cmd) et par le Dockerfile.
# Usage : sh bin/install-validator.sh [fichier cible, defaut bin/validator-cli.jar]
set -eu

VALIDATOR_VERSION=4.7.2
# sha256 de validator-cli.jar (a mettre a jour avec VALIDATOR_VERSION)
VALIDATOR_SHA256=b44bccfa1e099ba171af9a3f2fe3fd5212bd7cbbe72ce66f0705c1b73423075b

BIN_DIR=$(cd "$(dirname "$0")" && pwd)
TARGET="${1:-$BIN_DIR/validator-cli.jar}"

for cmd in curl sha256sum; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "install-validator : la commande '$cmd' est requise" >&2
        exit 1
    fi
done

# telechargement dans un fichier temporaire : la cible n'est remplacee que si l'empreinte est correcte
TMP_FILE="${TARGET}.tmp"
trap 'rm -f "$TMP_FILE"' EXIT

echo "install-validator : validator-cli v${VALIDATOR_VERSION} -> ${TARGET}"
curl -fsSL -o "$TMP_FILE" "https://github.com/IGNF/validator/releases/download/v${VALIDATOR_VERSION}/validator-cli.jar"
echo "${VALIDATOR_SHA256}  ${TMP_FILE}" | sha256sum -c -
mv "$TMP_FILE" "$TARGET"
