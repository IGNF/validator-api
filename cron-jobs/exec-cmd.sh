#!/bin/bash

ROOT_DIR="`dirname $0`/.."
CMD=$1

if [ -z $CMD ]; then
    echo "Command not specified"
    exit
fi

EXEC="docker exec -it backend php $ROOT_DIR/bin/console $CMD"
echo "Executing: $EXEC"
`$EXEC`
