#!/usr/bin/env bash
CI_REGISTRY_IMAGE_DEFAULT="example.com/opensource/base-images"
if [ -z ${CI_REGISTRY_IMAGE+x} ]; then
    echo "CI_REGISTRY_IMAGE is unset. defaulting to '$CI_REGISTRY_IMAGE_DEFAULT'";
    CI_REGISTRY_IMAGE=$CI_REGISTRY_IMAGE_DEFAULT
fi
echo "CI_REGISTRY_IMAGE is set to '$CI_REGISTRY_IMAGE'";

sed \
    -e "s|gone/marshall|$CI_REGISTRY_IMAGE/marshall|g" \
    -e "s|gone/php:core-|$CI_REGISTRY_IMAGE/php/core:|g" \
    -e "s|gone/php:cli-php|$CI_REGISTRY_IMAGE/php/cli:|g" \
    -e "s|gone/php:nginx-php|$CI_REGISTRY_IMAGE/php/nginx:|g" \
    -e "s|gone/php:apache-php|$CI_REGISTRY_IMAGE/php/apache:|g" \
    -e "s|gone/node:|$CI_REGISTRY_IMAGE/node:|g" \
    -e "s|gone/php:core|$CI_REGISTRY_IMAGE/php/core|g" \
    -e "s|gone/php:cli|$CI_REGISTRY_IMAGE/php/cli|g" \
    -e "s|gone/php:nginx|$CI_REGISTRY_IMAGE/php/nginx|g" \
    -e "s|gone/php:apache|$CI_REGISTRY_IMAGE/php/apache|g" \
    Makefile > Makefile.working