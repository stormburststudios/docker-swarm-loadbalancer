#!/usr/bin/env bash
TARGET=$1
cat .github/workflows/build-x86_64-marshall.yml \
    | sed "s|x86_64|${TARGET}|g" \
    | sed "s|/marshall|/marshall-${TARGET}|g" \
    > .github/workflows/build-${TARGET}-marshall.yml

cat .github/workflows/build-x86_64-php.yml \
    | sed "s|x86_64|${TARGET}|g" \
    | sed "s|/php|/php-${TARGET}|g" \
    > .github/workflows/build-${TARGET}-php.yml

