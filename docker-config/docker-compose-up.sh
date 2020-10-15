#! /bin/bash

if [ -z ${HTTP_PROXY} ]; then
    echo "no proxy";
    docker-compose up -d --build;
else
    echo "configurating proxy";
    docker-compose build --build-arg HTTP_PROXY=${HTTP_PROXY} --build-arg HTTPS_PROXY=${HTTPS_PROXY} --build-arg http_proxy=${http_proxy} --build-arg https_proxy=${https_proxy};
    docker-compose up -d;
fi