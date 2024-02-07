FROM php:nginx
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"
# hadolint ignore=DL3008
RUN apt-get update -qq && \
    apt-get install -yqq --no-install-recommends \
      php8.1-mailparse \
      cpulimit  \
    && \
    apt-get autoremove -yqq && \
        apt-get clean && \
        rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log
COPY nginx.runit /etc/service/nginx/run
COPY laravel.runit /etc/service/laravel/run
COPY laravel-horizon.runit /etc/service/horizon/run
COPY laravel-horizon.finish /etc/service/horizon/finish
COPY laravel-scheduler.runit /etc/service/scheduler/run
COPY migrate.runit /etc/service/migrate/run
COPY wait-for-mysql /usr/bin/wait-for-mysql
RUN chmod +x /etc/service/*/run /etc/service/*/finish /usr/bin/wait-for-mysql
HEALTHCHECK --interval=10s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

RUN adduser laravel
USER laravel
