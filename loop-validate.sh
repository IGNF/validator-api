#!/bin/bash
set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

while true
do
	php "${SCRIPT_DIR}/bin/console" ign-validator:validations:process-one -vv
	sleep 15
done

