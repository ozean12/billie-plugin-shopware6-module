#!/usr/bin/env bash

## break if an error occurs
set -e

BASEDIR=$(cd `dirname $0` && pwd)
PLUGIN_DIR=$(dirname "$BASEDIR")
PLUGIN_NAME=$(basename "$PLUGIN_DIR")
BUILD_DIR=$(dirname "PLUGIN_DIR")/build/

rm -rf "$BUILD_DIR"
mkdir -p build/dist
tar -C "$PLUGIN_DIR"/../ --exclude-from="$BASEDIR"/.build_exclude -czf "$BUILD_DIR"/dist.tar.gz "$PLUGIN_NAME"

mkdir -p "$BUILD_DIR"/dist/"$PLUGIN_NAME"
tar -xzf "$BUILD_DIR"/dist.tar.gz -C "$BUILD_DIR"/dist/
rm -rf "$BUILD_DIR"/dist.tar.gz

composer remove shopware/shopware --ignore-platform-reqs -o -d "$BUILD_DIR"/dist/"$PLUGIN_NAME"
composer install --no-dev --ignore-platform-reqs -o -d "$BUILD_DIR"/dist/"$PLUGIN_NAME"

rm -rf "$BUILD_DIR"/dist/"$PLUGIN_NAME"/vendor/billie/api-php-sdk/tests

(cd "$BUILD_DIR"/dist && zip -r "$PLUGIN_NAME".zip "$PLUGIN_NAME")
