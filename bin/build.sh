#!/usr/bin/env bash

## break if an error occurs
set -e

BASEDIR=$(cd `dirname $0` && pwd)
PLUGIN_DIR=$(dirname "$BASEDIR")
PLUGIN_NAME="BilliePayment"
BUILD_DIR=$(dirname "PLUGIN_DIR")/build/

rm -rf "$BUILD_DIR"
mkdir -p build/dist/"$PLUGIN_NAME"
tar --exclude-from="$BASEDIR"/.release_exclude -czf "$BUILD_DIR"/dist.tar.gz .
tar -xzf "$BUILD_DIR"/dist.tar.gz -C "$BUILD_DIR"/dist/"$PLUGIN_NAME"

composer remove shopware/shopware --ignore-platform-reqs -d "$BUILD_DIR"/dist/"$PLUGIN_NAME" --no-update
composer install --no-dev --ignore-platform-reqs -o -d "$BUILD_DIR"/dist/"$PLUGIN_NAME"

rm -rf "$BUILD_DIR"/dist/"$PLUGIN_NAME"/vendor/billie/api-php-sdk/tests
rm -rf "$BUILD_DIR"/dist/"$PLUGIN_NAME"/vendor/billie/api-php-sdk/.git

(cd "$BUILD_DIR"/dist && zip -r "$PLUGIN_NAME"-$(grep -Po '(?<=<version>)(.*)(?=</version>)' "$PLUGIN_NAME"/plugin.xml).zip "$PLUGIN_NAME")
