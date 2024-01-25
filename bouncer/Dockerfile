FROM benzine/php:cli-8.1 as bouncer
ARG BUILD_DATE
ARG GIT_SHA
ENV BUILD_DATE=${BUILD_DATE} \
    GIT_SHA=${GIT_SHA}

LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

# Install nginx, certbot
RUN apt-get -qq update && \
    # Install pre-dependencies to use apt-key.
    apt-get -yqq install --no-install-recommends \
        lsb-core \
        gnupg \
        && \
    # Add nginx ppa
    sh -c 'echo "deb http://ppa.launchpad.net/nginx/stable/ubuntu $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/nginx-stable.list' && \
    # Add nginx key
    apt-key adv --keyserver keyserver.ubuntu.com --recv-keys C300EE8C && \
    # Update
    apt-get -qq update && \
    # Install Nginx, Certbot bits and apache2-utils for htpasswd generation
    apt-get -yqq install --no-install-recommends \
        nginx \
        python3-certbot-nginx \
        apache2-utils \
        && \
    # Cleanup
    apt-get remove -yqq \
        lsb-core \
        cups-common \
        && \
    apt-get autoremove -yqq && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/lib/dpkg/status.old /var/cache/debconf/templates.dat /var/log/dpkg.log /var/log/lastlog /var/log/apt/*.log

COPY self-signed-certificates /certs
COPY nginx.runit /etc/service/nginx/run
COPY logs.runit /etc/service/nginx-logs/run
COPY bouncer.runit /etc/service/bouncer/run
COPY bouncer.finish /etc/service/bouncer/finish
COPY logs-nginx-access.runit /etc/service/logs-nginx-access/run
COPY logs-nginx-error.runit /etc/service/logs-nginx-error/run
RUN chmod +x /etc/service/*/run /etc/service/*/finish
COPY NginxDefault /etc/nginx/sites-enabled/default.conf
COPY Nginx-tweak.conf /etc/nginx/conf.d/tweak.conf
# Disable daemonising in nginx
RUN sed -i '1s;^;daemon off\;\n;' /etc/nginx/nginx.conf && \
    sed -i 's|include /etc/nginx/sites-enabled/*|include /etc/nginx/sites-enabled/*.conf|g' /etc/nginx/nginx.conf && \
    rm /etc/nginx/sites-enabled/default && \
    rm -R /etc/nginx/sites-available

COPY vendor /app/vendor
COPY composer.* /app/
RUN composer install

COPY public /app/public
COPY bin /app/bin
COPY src /app/src
COPY templates /app/templates

VOLUME /etc/letsencrypt
VOLUME /var/log/bouncer

RUN chmod +x /app/bin/bouncer && \
    mkdir -p /var/log/bouncer

FROM benzine/php:nginx-8.1 as test-app-a
COPY ./test/public-web-a /app/public

FROM benzine/php:nginx-8.1 as test-app-b
COPY ./test/public-web-b /app/public

FROM benzine/php:nginx-8.1 as test-app-c
COPY ./test/public-web-c /app/public

