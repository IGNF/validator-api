#!/bin/bash

set -e

VERSION="4.5.0.2216-linux"
PWD=$(pwd)

echo "Installing sonar-scanner-cli-${VERSION}"

curl "https://binaries.sonarsource.com/Distribution/sonar-scanner-cli/sonar-scanner-cli-${VERSION}.zip" >"sonar-scanner-cli-${VERSION}.zip"

unzip -o "sonar-scanner-cli-${VERSION}.zip"

rm -rf sonar-scanner
mv "sonar-scanner-${VERSION}" sonar-scanner

rm "sonar-scanner-cli-${VERSION}.zip"
