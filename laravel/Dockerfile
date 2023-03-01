FROM benzine/php:nginx-${PHP_VERSION}
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"
RUN apt-get update -qq && \
    apt-get install -yqq \
      php8.1-mailparse \
      cpulimit
COPY laravel.runit /etc/service/laravel/run
COPY laravel-horizon.runit /etc/service/horizon/run
COPY laravel-horizon.finish /etc/service/horizon/finish
COPY laravel-scheduler.runit /etc/service/scheduler/run
COPY migrate.runit /etc/service/migrate/run
COPY wait-for-mysql /usr/bin/wait-for-mysql
RUN chmod +x /etc/service/*/run /etc/service/*/finish /usr/bin/wait-for-mysql