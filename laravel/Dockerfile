FROM benzine/php:nginx-8.0
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY laravel.runit /etc/service/laravel/run
RUN chmod +x /etc/service/laravel/run