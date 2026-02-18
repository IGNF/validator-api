#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

RUNNING=1

_term(){
	echo "$BASH_SOURCE - caught signal, terminating..."
	RUNNING=0
	kill -s SIGTERM $child_pid
	wait
}


# Note that trapping SIGWINCH is due to weird usage of this "window resized" by apache2
# (see https://bz.apache.org/bugzilla/show_bug.cgi?id=50669)
# ...propagated to STOPSIGNAL=SIGWINCH in php:8.2-apache docker image
# ...which leads to an unexpected behavior of "docker stop"
# ...and probably problems with PHP applications including console commands (Symfony, Laravel,...) 
trap _term SIGTERM SIGINT SIGWINCH

echo "$BASH_SOURCE - started with PID=$$"

if [ $RUNNING -eq 1 ]
do
	php "${SCRIPT_DIR}/bin/console" ign-validator:validations:cleanup -vvv &
	child_pid=$!
	wait $child_pid
done

echo "$BASH_SOURCE - ended"
