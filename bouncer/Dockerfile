FROM benzine/php:cli-8.1 as bouncer
LABEL maintainer="Matthew Baggett <matthew@baggett.me>" \
      org.label-schema.vcs-url="https://github.com/benzine-framework/docker" \
      org.opencontainers.image.source="https://github.com/benzine-framework/docker"

COPY self-signed-certificates /certs

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

VOLUME /etc/letsencrypt

COPY nginx.runit /etc/service/nginx/run
COPY logs.runit /etc/service/nginx-logs/run
COPY bouncer.runit /etc/service/bouncer/run
COPY logs-nginx-access.runit /etc/service/logs-nginx-access/run
COPY logs-nginx-error.runit /etc/service/logs-nginx-error/run
RUN chmod +x /etc/service/*/run
COPY NginxDefault /etc/nginx/sites-enabled/default
COPY NginxSSL /etc/nginx/sites-enabled/default-ssl
COPY Nginx-tweak.conf /etc/nginx/conf.d/tweak.conf
COPY NginxTemplate.twig /app/
# Disable daemonising in nginx
RUN sed -i '1s;^;daemon off\;\n;' /etc/nginx/nginx.conf
RUN sed -i 's|include /etc/nginx/sites-enabled/*|include /etc/nginx/sites-enabled/*.conf|g' /etc/nginx/nginx.conf
COPY bouncer /app
COPY composer.* /app/
COPY public /app/public
RUN composer install && \
    chmod +x /app/bouncer && \
    mkdir -p /var/log/bouncer && \
    rm /etc/nginx/sites-enabled/default && \
    cp /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

FROM benzine/php:nginx-8.1 as test-app-a
COPY ./test/public-web-a /app/public

FROM benzine/php:nginx-8.1 as test-app-b
COPY ./test/public-web-b /app/public

FROM benzine/php:nginx-8.1 as test-app-c
COPY ./test/public-web-c /app/public

