#!/usr/bin/env bash

BASEDIR=$(cd `dirname $0` && pwd)
PLUGIN_DIR=$(dirname "$BASEDIR")
BUILD_DIR=$(dirname "PLUGIN_DIR")/build/

rm -rf build/
mkdir -p build/
tar -C "$PLUGIN_DIR"/../ --exclude-from="$BASEDIR"/.build_exclude -czf "$BUILD_DIR"/dist.tar.gz BilliePayment
rm -rf "$BUILD_DIR"/dist/BilliePayment
mkdir -p "$BUILD_DIR"/dist/BilliePayment
tar -xzf "$BUILD_DIR"/dist.tar.gz -C "$BUILD_DIR"/dist/

rm -r "$BUILD_DIR"/dist/BilliePayment/composer*
composer install --ignore-platform-reqs --no-dev -o -d "$BUILD_DIR"/dist/BilliePayment/lib/api-php-sdk

rm -rf "$BUILD_DIR"/dist.tar.gz

(cd "$BUILD_DIR"/dist && zip -r BilliePayment.zip BilliePayment)
