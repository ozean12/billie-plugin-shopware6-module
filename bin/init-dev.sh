#!/usr/bin/env bash

BASEDIR=$(cd `dirname $0` && pwd)
PLUGIN_DIR=$(dirname "$BASEDIR")

composer install --ignore-platform-reqs -d "$PLUGIN_DIR"/lib/api-php-sdk
composer install --ignore-platform-reqs -d "$PLUGIN_DIR"
